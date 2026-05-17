<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Vendor\Components;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Communication\Application\Actions\SendVendorChatMessageAction;
use App\Modules\Communication\Application\DTOs\SendVendorChatMessageDTO;
use App\Modules\Communication\Application\Services\ChatPanelStateResolver;
use App\Modules\Communication\Domain\Enums\ChatPanelState;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RestrictedChatPanel extends Component
{
    // -----------------------------------------------------------------
    // Public properties (wire-bound / passed from host)
    // -----------------------------------------------------------------

    public string $bookingPublicId = '';

    public string $body = '';

    public string $flashMessage = '';

    public bool $isBlocked = false;

    // -----------------------------------------------------------------
    // Private state (populated in mount, not wire-bound)
    // -----------------------------------------------------------------

    private Booking $booking;

    private ?ChatThread $thread = null;

    private VendorProfile $vendorProfile;

    // -----------------------------------------------------------------
    // Lifecycle
    // -----------------------------------------------------------------

    public function mount(string $bookingPublicId): void
    {
        $this->bookingPublicId = $bookingPublicId;

        // Load vendor profile for the authenticated user
        $this->vendorProfile = VendorProfile::query()
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Load booking via public_id
        $this->booking = Booking::query()
            ->where('public_id', $bookingPublicId)
            ->firstOrFail();

        // Load chat thread for this booking+vendor (may be null)
        $this->thread = ChatThread::query()
            ->where('booking_id', $this->booking->id)
            ->where('vendor_profile_id', $this->vendorProfile->id)
            ->first();
    }

    // -----------------------------------------------------------------
    // Computed properties
    // -----------------------------------------------------------------

    #[Computed]
    public function state(): ChatPanelState
    {
        return app(ChatPanelStateResolver::class)
            ->resolve($this->thread, $this->booking);
    }

    #[Computed]
    public function messages(): Collection
    {
        if ($this->thread === null) {
            return collect();
        }

        return $this->thread->messages()->get();
    }

    // -----------------------------------------------------------------
    // Actions
    // -----------------------------------------------------------------

    public function sendMessage(): void
    {
        $this->validate([
            'body' => 'required|string|max:2000',
        ]);

        try {
            app(SendVendorChatMessageAction::class)->execute(
                $this->thread,
                $this->vendorProfile,
                new SendVendorChatMessageDTO($this->body),
            );
        } catch (\DomainException $e) {
            if (str_contains($e->getMessage(), 'Duplicate message submission')) {
                return;
            }

            $this->flashMessage = __('communication::chat.message_blocked');
            $this->isBlocked = true;

            return;
        }

        $this->body = '';
        $this->isBlocked = false;
        $this->flashMessage = __('communication::chat.message_sent');

        // Refresh thread so the messages computed property returns fresh rows
        $this->thread = $this->thread->fresh();
    }

    // -----------------------------------------------------------------
    // Rendering
    // -----------------------------------------------------------------

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('vendor-portal.components.restricted-chat-panel');
    }
}
