<?php

namespace App\Http\Controllers\Procurement;

use App\DataTables\Procurement\BiddingsDataTable;
use App\Enums\BiddingStatus;
use App\Enums\BiddingStep;
use App\Enums\Region;
use App\Exceptions\ProcurementException;
use App\Http\Controllers\Controller;
use App\Models\Procurement\Bidding;
use App\Models\Procurement\FundSource;
use App\Services\Procurement\BiddingService;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Competitive (public) bidding: the BAC Secretariat puts Competitive Bidding lines of submitted PRs / JRs
 * into lots and records each step of the bidding. {bidding} is the uuid.
 */
class BiddingController extends Controller
{
    public function __construct(
        protected BiddingService $service
    ) {}

    /** The page, or (for the table's requests) one page of biddings as JSON. */
    public function index(Request $request, BiddingsDataTable $dataTable)
    {
        return $dataTable->render('procurement.biddings.index', [
            'status'  => BiddingStatus::tryFrom((string) $request->query('status')),
            'year'    => BiddingsDataTable::selectedYear(),
            'years'   => Bidding::yearOptions(Bidding::visibleTo($request->user())),
            'regions' => $request->user()->hasRole('Super Admin') ? Region::cases() : [],
            'funds'   => FundSource::active()->orderBy('code')->get(),
        ]);
    }

    /** A new bidding, numbered now for its fund source (2026-SIDA-0001). */
    public function store(Request $request)
    {
        $user = $request->user();
        $data = $this->validateJson($request, [
            'fund_source_id' => ['required', 'integer', Rule::exists('fund_sources', 'id')->where('is_active', true)],
            'title'          => ['nullable', 'string', 'max:500'],
            'region'         => ['nullable', Rule::enum(Region::class)],
        ], ['fund_source_id.required' => 'Choose the fund source; the bidding is numbered for it.']);

        // Only a Super Admin picks the region; everyone else works in their own
        $region = $user->hasRole('Super Admin') && ! empty($data['region']) ? Region::from($data['region']) : ($user->region ?? Region::Lm);
        $bidding = $this->service->create($user, $region, FundSource::findOrFail($data['fund_source_id']), $data);

        return response()->json(['status' => 'success', 'message' => "{$bidding->title()} created. Add the items to bid, in lots.", 'url' => route('procurement.biddings.show', $bidding)]);
    }

    public function show(Request $request, Bidding $bidding)
    {
        $this->authorizeView($request, $bidding);
        $bidding->load(['fundSource', 'lots.items.line.request.office', 'events.user', 'creator']);

        return view('procurement.biddings.show', [
            'bidding' => $bidding,
            'next'    => $this->service->nextSteps($bidding),
        ]);
    }

    public function title(Request $request, Bidding $bidding)
    {
        $this->authorizeView($request, $bidding);
        $data = $this->validateJson($request, ['title' => ['nullable', 'string', 'max:500']]);

        return $this->attempt(function () use ($request, $bidding, $data) {
            $this->service->updateTitle($bidding, $request->user(), $data['title'] ?? null);

            return 'Saved.';
        });
    }

    /** Add items: submitted PRs / JRs of the region with Competitive Bidding lines in no other bidding. */
    public function picker(Request $request, Bidding $bidding)
    {
        $this->authorizeView($request, $bidding);

        if (! $bidding->lotsEditable()) {
            $this->deny($request, "{$bidding->title()} is advertised; its lots can no longer change.");
        }

        return view('procurement.biddings.extras.picker', [
            'bidding'  => $bidding->load(['fundSource', 'lots']),
            'requests' => $this->service->availableRequests($bidding),
            'lotId'    => $request->integer('lot') ?: null,
        ]);
    }

    public function addLines(Request $request, Bidding $bidding)
    {
        $this->authorizeView($request, $bidding);
        $data = $this->validateJson($request, [
            'lines'   => ['required', 'array', 'min:1'], 'lines.*' => ['integer'],
            'lot_id'  => ['nullable', 'integer'],
        ], ['lines.required' => 'Choose at least one item.']);

        return $this->attempt(function () use ($request, $bidding, $data) {
            $this->service->addLines($bidding, $request->user(), $data['lines'], $data['lot_id'] ?? null);
            $n = count($data['lines']);

            return $n === 1 ? '1 item added.' : "{$n} items added.";
        });
    }

    public function removeLine(Request $request, Bidding $bidding)
    {
        $this->authorizeView($request, $bidding);
        $this->validateJson($request, ['id' => ['required', 'integer']]);

        return $this->attempt(function () use ($request, $bidding) {
            $this->service->removeLine($bidding, $request->user(), $request->integer('id'));

            return 'Item taken off the bidding.';
        });
    }

    public function moveLine(Request $request, Bidding $bidding)
    {
        $this->authorizeView($request, $bidding);
        $data = $this->validateJson($request, ['id' => ['required', 'integer'], 'lot_id' => ['required', 'integer']]);

        return $this->attempt(function () use ($request, $bidding, $data) {
            $this->service->moveLine($bidding, $request->user(), $data['id'], $data['lot_id']);

            return 'Item moved.';
        });
    }

    /** A new lot (no id), or a lot's title. */
    public function saveLot(Request $request, Bidding $bidding)
    {
        $this->authorizeView($request, $bidding);
        $data = $this->validateJson($request, ['id' => ['nullable', 'integer'], 'title' => ['nullable', 'string', 'max:500']]);

        return $this->attempt(function () use ($request, $bidding, $data) {
            $lot = $this->service->saveLot($bidding, $request->user(), $data['id'] ?? null, $data['title'] ?? null);

            return empty($data['id']) ? "{$lot->name()} added." : 'Saved.';
        });
    }

    public function deleteLot(Request $request, Bidding $bidding)
    {
        $this->authorizeView($request, $bidding);
        $this->validateJson($request, ['id' => ['required', 'integer']]);

        return $this->attempt(function () use ($request, $bidding) {
            $this->service->deleteLot($bidding, $request->user(), $request->integer('id'));

            return 'Lot removed.';
        });
    }

    /** The form of the next step (dates, links, remarks). */
    public function stepEntry(Request $request, Bidding $bidding, string $step)
    {
        $this->authorizeView($request, $bidding);
        $step = BiddingStep::tryFrom($step) ?? abort(404);

        if (! in_array($step, $this->service->nextSteps($bidding), true)) {
            $this->deny($request, 'That step cannot be recorded now.');
        }

        return view('procurement.biddings.extras.step_entry', ['bidding' => $bidding, 'step' => $step]);
    }

    public function recordStep(Request $request, Bidding $bidding, string $step)
    {
        $this->authorizeView($request, $bidding);
        $step = BiddingStep::tryFrom($step) ?? abort(404);
        $data = $this->validateJson($request, $this->stepRules($step), [
            'date.required'       => 'Set the date.',
            'preproc_at.required' => 'Set the date and time of the conference.',
            'bidding_at.required' => 'Set the date and time of the public bidding.',
        ], ['philgeps_link' => 'PhilGEPS link', 'website_link' => 'website link', 'prebid_at' => 'pre-bid conference', 'bidding_at' => 'public bidding']);

        return $this->attempt(function () use ($request, $bidding, $step, $data) {
            $bidding = $this->service->recordStep($bidding, $request->user(), $step, $data);

            return "{$step->label()}: recorded.";
        });
    }

    public function cancel(Request $request, Bidding $bidding)
    {
        $this->authorizeView($request, $bidding);
        $data = $this->validateJson($request, ['reason' => ['required', 'string', 'max:500']], ['reason.required' => 'Please state the reason for cancelling.']);

        return $this->attempt(function () use ($request, $bidding, $data) {
            $this->service->cancel($bidding, $request->user(), $data['reason']);

            return "{$bidding->title()} cancelled. Its items can go to another bidding.";
        });
    }

    protected function stepRules(BiddingStep $step): array
    {
        $link = ['nullable', 'url', 'max:1000'];

        return match ($step) {
            BiddingStep::PreprocSet => ['preproc_at' => ['required', 'date'], 'remarks' => ['nullable', 'string', 'max:1000']],
            BiddingStep::Advertised => [
                'date' => ['required', 'date'], 'bidding_at' => ['required', 'date'], 'prebid_at' => ['nullable', 'date'],
                'philgeps_link' => $link, 'website_link' => $link, 'remarks' => ['nullable', 'string', 'max:1000'],
            ],
            BiddingStep::PrebidHeld, BiddingStep::Supplemental => [
                'date' => ['required', 'date'], 'bidding_at' => ['nullable', 'date'], 'website_link' => $link, 'remarks' => ['nullable', 'string', 'max:1000'],
            ],
            default => ['date' => ['required', 'date'], 'remarks' => ['nullable', 'string', 'max:1000']],
        };
    }

    protected function attempt(Closure $action): JsonResponse
    {
        try {
            return response()->json(['status' => 'success', 'message' => $action()]);
        } catch (ProcurementException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    protected function authorizeView(Request $request, Bidding $bidding): void
    {
        if (! Bidding::visibleTo($request->user())->whereKey($bidding->id)->exists()) {
            $this->deny($request, 'This bidding belongs to another region.');
        }
    }

    protected function deny(Request $request, string $message): never
    {
        abort($request->expectsJson() ? response()->json(['status' => 'error', 'message' => $message], 403) : 403, $message);
    }

    protected function validateJson(Request $request, array $rules, array $messages = [], array $attributes = []): array
    {
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(['status' => 'error', 'errors' => $validator->errors()], 422));
        }

        return $validator->validated();
    }
}
