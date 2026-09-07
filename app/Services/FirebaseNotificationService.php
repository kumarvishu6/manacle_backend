<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;

class FirebaseNotificationService
{
    protected $messaging;

    public function __construct()
    {
        $credentialsPath = base_path(config('services.firebase.credentials'));

        $factory = (new Factory())->withServiceAccount($credentialsPath);
        $this->messaging = $factory->createMessaging();
    }

    /**
     * Sends a push notification to every device registered to this user.
     * Best-effort by design: a failed push should never break the actual
     * booking action (starting/completing a booking) that triggered it.
     */
    public function notifyUser(User $user, string $title, string $body, array $data = []): void
    {
        $tokens = $user->pushTokens()->pluck('token')->toArray();

        if (empty($tokens)) {
            return;
        }

        foreach ($tokens as $token) {
            try {
                $message = CloudMessage::new()
                    ->withNotification(FirebaseNotification::create($title, $body))
                    ->withData($data)
                    ->withToken($token);

                $this->messaging->send($message);
            } catch (\Throwable $e) {
                // A single bad/expired token shouldn't stop the loop or
                // break the calling code. Just log it and move on.
                Log::warning('Push notification failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}