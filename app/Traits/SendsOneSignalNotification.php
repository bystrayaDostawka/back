<?php

namespace App\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait SendsOneSignalNotification
{
    /**
     * Отправить уведомление конкретному пользователю
     */
    protected function sendNotificationToUser($userId, $heading, $content, $data = [])
    {
        $user = \App\Models\User::find($userId);

        if (!$user || !$user->onesignal_player_id) {
            Log::warning("User {$userId} has no OneSignal Player ID");
            return false;
        }

        return $this->sendPushNotification(
            [$user->onesignal_player_id],
            $heading,
            $content,
            $data
        );
    }

    /**
     * Отправить уведомление всем курьерам
     */
    protected function sendNotificationToCouriers($heading, $content, $data = [])
    {
        $couriers = \App\Models\User::where('role', 'courier')
            ->whereNotNull('onesignal_player_id')
            ->pluck('onesignal_player_id')
            ->toArray();

        if (empty($couriers)) {
            Log::warning("No couriers with OneSignal Player ID found");
            return false;
        }

        return $this->sendPushNotification($couriers, $heading, $content, $data);
    }

    /**
     * Отправить пуш-уведомление через OneSignal API
     */
    protected function sendPushNotification($playerIds, $heading, $content, $data = [])
    {
        $appId = config('onesignal.app_id');
        $restApiKey = config('onesignal.rest_api_key');

        if (!$appId || !$restApiKey) {
            Log::error('OneSignal credentials not configured');
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Basic {$restApiKey}",
            ])->post('https://onesignal.com/api/v1/notifications', [
                'app_id' => $appId,
                'include_player_ids' => $playerIds,
                'headings' => ['en' => $heading],
                'contents' => ['en' => $content],
                'data' => $data,
            ]);

            if ($response->successful()) {
                Log::info('OneSignal notification sent successfully', [
                    'recipients' => count($playerIds),
                    'heading' => $heading,
                ]);
                return true;
            } else {
                Log::error('OneSignal notification failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('OneSignal notification exception', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

