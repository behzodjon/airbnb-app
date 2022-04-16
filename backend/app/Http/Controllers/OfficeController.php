<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Office;
use App\Models\Reservation;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\OfficeResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Validators\OfficeValidator;
use App\Notifications\OfficePendingApproval;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OfficeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $offices = Office::query()
            ->where('approval_status', Office::APPROVAL_APPROVED)
            ->where('hidden', false)
            ->when(request('user_id'), fn ($builder) => $builder->whereUserId(request('user_id')))
            ->when(
                request('visitor_id'),
                fn (Builder $builder)
                => $builder->whereRelation('reservations', 'user_id', '=', request('visitor_id'))
            )
            ->when(
                request('lat') && request('lng'),
                fn ($builder) => $builder->nearestTo(request('lat'), request('lng')),
                fn ($builder) => $builder->orderBy('id', 'ASC')
            )
            ->with(['tags', 'images', 'user'])
            ->withCount(['reservations' => fn ($builder) => $builder->where('status', Reservation::STATUS_ACTIVE)])
            ->latest('id')
            ->paginate(20);

        return OfficeResource::collection(
            $offices
        );
    }

    public function show(Office $office)
    {
        $office->loadCount(['reservations' => fn ($builder) => $builder->where('status', Reservation::STATUS_ACTIVE)])
            ->load(['tags', 'images', 'user']);

        return OfficeResource::make(
            $office
        );
    }

    public function create(Office $office): JsonResource
    {

        if (!auth()->user()->tokenCan('office.create')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $attributes = (new OfficeValidator())->validate(
            $office = new Office(),
            request()->all()
        );

        $attributes['approval_status'] = Office::APPROVAL_PENDING;
        $attributes['user_id'] = auth()->id;

        $office = DB::transaction(function () use ($office, $attributes) {
            $office->fill(
                Arr::except($attributes, ['tags'])
            )->save();

            if (isset($attributes['tags'])) {
                $office->tags()->attach($attributes['tags']);
            }
            return $office;
        });


        return OfficeResource::make(
            $office->load(['images', 'tags', 'user'])
        );
    }

    public function update(Office $office): JsonResource
    {
        if (!auth()->user()->tokenCan('office.update')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $this->authorize($office);

        $attributes = (new OfficeValidator())->validate(
            $office,
            request()->all()
        );
        $office->fill(Arr::except($attributes, ['tags']));

        if ($requiresReview = $office->isDirty(['lat', 'lng', 'price_per_day'])) {
            $office->fill(['approval_status' => Office::APPROVAL_PENDING]);
        }

        DB::transaction(function () use ($office, $attributes) {
            $office->save();

            if (isset($attributes['tags'])) {
                $office->tags()->sync($attributes['tags']);
            }
        });

        if ($requiresReview) {
            Notification::send(User::where('is_admin', true)->get(), new OfficePendingApproval($office));
        }

        return OfficeResource::make(
            $office->load(['images', 'tags', 'user'])
        );
    }

    public function delete(Office $office)
    {
        abort_unless(auth()->user()->tokenCan('office.delete'),
            Response::HTTP_FORBIDDEN
        );

        $this->authorize('delete', $office);

        throw_if(
            $office->reservations()->where('status', Reservation::STATUS_ACTIVE)->exists(),
            ValidationException::withMessages(['office' => 'Cannot delete this office!'])
        );

        $office->images()->each(function ($image) {
            Storage::delete($image->path);

            $image->delete();
        });

        $office->delete();
    }
}
