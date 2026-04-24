<?php

namespace App\Services;

use App\Models\FollowUpAction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class ReminderService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function send(FollowUpAction $followUp, string $channel): void
    {
        $channel = strtolower($channel);
        $targetName = $followUp->customer?->name ?? $followUp->supplier?->name ?? 'Contact';
        $reference = $followUp->sale?->sale_no ?? $followUp->purchase?->purchase_no ?? 'Account';
        $message = "Reminder for {$targetName} regarding {$reference}. ".$followUp->notes;

        if ($channel === 'email') {
            $email = $followUp->customer?->email ?? $followUp->supplier?->email ?? null;

            if (! $email) {
                throw new RuntimeException('No email address is available for this follow-up target.');
            }

            Mail::raw($message, function ($mail) use ($email, $targetName, $reference): void {
                $mail->to($email)->subject("Follow-up Reminder: {$reference} for {$targetName}");
            });
        } elseif ($channel === 'sms') {
            $phone = $followUp->customer?->phone ?? $followUp->supplier?->phone ?? null;

            if (! $phone) {
                throw new RuntimeException('No phone number is available for this follow-up target.');
            }

            Log::info('SMS reminder queued', [
                'phone' => $phone,
                'message' => $message,
                'follow_up_id' => $followUp->id,
            ]);
        } else {
            throw new RuntimeException('Unsupported reminder channel.');
        }

        $followUp->update([
            'channel' => strtoupper($channel),
            'last_sent_at' => now(),
            'status' => 'sent',
        ]);

        $this->auditLogService->record('follow_up.sent', $followUp, "Reminder sent by {$channel}.", [
            'channel' => $channel,
            'reference' => $reference,
        ]);
    }
}
