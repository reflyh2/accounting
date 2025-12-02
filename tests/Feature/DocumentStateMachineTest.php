<?php

use App\Domain\Documents\StateMachine\Definitions\PurchaseOrderStates;
use App\Domain\Documents\StateMachine\DocumentStateMachineDefinition;
use App\Domain\Documents\StateMachine\DocumentStateTransition;
use App\Enums\Documents\PurchaseOrderStatus;
use App\Events\Documents\DocumentStatusChanged;
use App\Exceptions\DocumentStateException;
use App\Models\User;
use App\Traits\DocumentStateMachine;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('document_state_machine_tests', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('created_by')->nullable();
        $table->string('status');
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('document_state_machine_tests');
});

it('transitions purchase order states and dispatches events', function () {
    Event::fake();

    $document = TestPurchaseOrderDocument::create([
        'status' => PurchaseOrderStatus::DRAFT->value,
    ]);

    $document->transitionTo(PurchaseOrderStatus::APPROVED);

    expect($document->fresh()->status)->toBe(PurchaseOrderStatus::APPROVED->value);

    Event::assertDispatched(DocumentStatusChanged::class, function (DocumentStatusChanged $event) use ($document) {
        return $event->document->is($document)
            && $event->from === PurchaseOrderStatus::DRAFT
            && $event->to === PurchaseOrderStatus::APPROVED;
    });
});

it('blocks transitions when not declared', function () {
    $document = TestPurchaseOrderDocument::create([
        'status' => PurchaseOrderStatus::DRAFT->value,
    ]);

    $document->transitionTo(PurchaseOrderStatus::APPROVED);

    expect(fn () => $document->transitionTo(PurchaseOrderStatus::DRAFT))
        ->toThrow(DocumentStateException::class);
});

it('enforces maker-checker guard and allowed options', function () {
    $maker = User::factory()->create();
    $checker = User::factory()->create();

    $document = GuardedPurchaseOrderDocument::create([
        'status' => PurchaseOrderStatus::DRAFT->value,
        'created_by' => $maker->id,
    ]);

    $allowedForMaker = $document->allowedDocumentStatuses($maker, ['enforceMakerChecker' => true]);
    expect($allowedForMaker)->not()->toContain(PurchaseOrderStatus::APPROVED);

    expect(fn () => $document->transitionTo(PurchaseOrderStatus::APPROVED, $maker, ['enforceMakerChecker' => true]))
        ->toThrow(DocumentStateException::class);

    $allowedForChecker = $document->allowedDocumentStatuses($checker, ['enforceMakerChecker' => true]);
    expect($allowedForChecker)->toContain(PurchaseOrderStatus::APPROVED);

    $document->transitionTo(PurchaseOrderStatus::APPROVED, $checker, ['enforceMakerChecker' => true]);

    expect($document->fresh()->status)->toBe(PurchaseOrderStatus::APPROVED->value);
});

it('enforces global maker-checker guard when enabled', function () {
    config()->set('purchasing.maker_checker.enforce', true);

    $maker = User::factory()->create();
    $checker = User::factory()->create();

    $document = TestPurchaseOrderDocument::create([
        'status' => PurchaseOrderStatus::DRAFT->value,
        'created_by' => $maker->id,
    ]);

    expect(fn () => $document->transitionTo(PurchaseOrderStatus::APPROVED, $maker))
        ->toThrow(DocumentStateException::class);

    $document->transitionTo(PurchaseOrderStatus::APPROVED, $checker);

    expect($document->fresh()->status)->toBe(PurchaseOrderStatus::APPROVED->value);

    config()->set('purchasing.maker_checker.enforce', false);
});

class TestPurchaseOrderDocument extends Model
{
    use DocumentStateMachine;

    protected $table = 'document_state_machine_tests';

    protected $guarded = [];

    protected static function stateMachineDefinition(): DocumentStateMachineDefinition
    {
        return PurchaseOrderStates::definition();
    }
}

final class GuardedPurchaseOrderDocument extends TestPurchaseOrderDocument
{
    protected static function stateMachineDefinition(): DocumentStateMachineDefinition
    {
        $definition = PurchaseOrderStates::definition();

        $definition->allow(
            DocumentStateTransition::make(PurchaseOrderStatus::DRAFT, PurchaseOrderStatus::APPROVED)
                ->guard(function (Model $document, ?Authenticatable $actor, array $context) {
                    if (($context['enforceMakerChecker'] ?? false)
                        && $actor
                        && (int) $document->created_by === (int) $actor->getAuthIdentifier()
                    ) {
                        throw new DocumentStateException('Maker cannot approve their own document.');
                    }

                    return true;
                })
        );

        return $definition;
    }
}

