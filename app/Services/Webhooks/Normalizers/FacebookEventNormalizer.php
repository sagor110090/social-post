<?php

namespace App\Services\Webhooks\Normalizers;

use App\Models\WebhookEvent;
use Illuminate\Support\Arr;

class FacebookEventNormalizer extends BaseEventNormalizer
{
    public function extractEventType(WebhookEvent $webhookEvent): string
    {
        $payload = $webhookEvent->payload;
        $change = Arr::get($payload, 'entry.0.changes.0');

        if (!$change) {
            return $webhookEvent->event_type;
        }

        $field = $change['field'] ?? '';
        $value = $change['value'] ?? [];

        return match ($field) {
            'feed' => match (true) {
                isset($value['post_id']) && isset($value['created_time']) => 'created',
                isset($value['post_id']) && isset($value['verb']) && $value['verb'] === 'edited' => 'updated',
                isset($value['post_id']) && isset($value['verb']) && $value['verb'] === 'removed' => 'deleted',
                isset($value['post_id']) && isset($value['like_count']) => 'engagement',
                default => 'unknown',
            },
            'conversations' => 'message_received',
            'leadgen' => 'lead_generated',
            'messaging_postbacks' => 'postback_received',
            'messaging_optins' => 'optin_received',
            'messaging_referrals' => 'referral_received',
            'messaging_handovers' => 'handover_received',
            'messaging_policy_enforcement' => 'policy_enforcement',
            'message_echoes' => 'message_sent',
            'message_reads' => 'message_read',
            'standby' => 'standby_event',
            'user_privacy' => 'privacy_changed',
            'page_change' => 'account_updated',
            default => $webhookEvent->event_type,
        };
    }

    public function extractObjectType(WebhookEvent $webhookEvent): string
    {
        $payload = $webhookEvent->payload;
        $change = Arr::get($payload, 'entry.0.changes.0');

        if (!$change) {
            return 'unknown';
        }

        $field = $change['field'] ?? '';
        $value = $change['value'] ?? [];

        return match ($field) {
            'feed' => 'post',
            'conversations', 'messaging_postbacks', 'messaging_optins', 'messaging_referrals',
            'messaging_handovers', 'messaging_policy_enforcement', 'message_echoes', 'message_reads' => 'message',
            'leadgen' => 'lead',
            'user_privacy' => 'user',
            'page_change' => 'account',
            'story_insights' => 'story',
            default => match (true) {
                isset($value['post_id']) => 'post',
                isset($value['comment_id']) => 'comment',
                isset($value['leadgen_id']) => 'lead',
                isset($value['story_id']) => 'story',
                default => 'unknown',
            },
        };
    }

    public function extractObjectId(WebhookEvent $webhookEvent): ?string
    {
        $payload = $webhookEvent->payload;
        
        // Try multiple paths for object ID
        $paths = [
            'entry.0.changes.0.value.post_id',
            'entry.0.changes.0.value.comment_id',
            'entry.0.changes.0.value.leadgen_id',
            'entry.0.changes.0.value.story_id',
            'entry.0.messaging.0.message.mid',
            'entry.0.messaging.0.sender.id',
            'entry.0.id',
        ];

        foreach ($paths as $path) {
            $id = Arr::get($payload, $path);
            if ($id) {
                return $id;
            }
        }

        return null;
    }

    protected function extractPlatformSpecificMetrics(array $payload): array
    {
        $metrics = [];
        $change = Arr::get($payload, 'entry.0.changes.0.value', []);

        // Facebook-specific metrics
        $metrics['video_views'] = Arr::get($change, 'video_views');
        $metrics['video_avg_watch_time'] = Arr::get($change, 'video_avg_watch_time');
        $metrics['video_total_watch_time'] = Arr::get($change, 'video_total_watch_time');
        $metrics['page_impressions'] = Arr::get($change, 'page_impressions');
        $metrics['page_impressions_unique'] = Arr::get($change, 'page_impressions_unique');
        $metrics['page_engaged_users'] = Arr::get($change, 'page_engaged_users');
        $metrics['page_post_engagements'] = Arr::get($change, 'page_post_engagements');
        $metrics['page_fan_adds'] = Arr::get($change, 'page_fan_adds');
        $metrics['page_fan_removes'] = Arr::get($change, 'page_fan_removes');
        $metrics['page_views_total'] = Arr::get($change, 'page_views_total');
        $metrics['page_views_login_total'] = Arr::get($change, 'page_views_login_total');
        $metrics['page_views_logout_total'] = Arr::get($change, 'page_views_logout_total');
        $metrics['post_clicks'] = Arr::get($change, 'post_clicks');
        $metrics['post_negative_feedback'] = Arr::get($change, 'post_negative_feedback');
        $metrics['post_negative_feedback_hide'] = Arr::get($change, 'post_negative_feedback_hide');
        $metrics['post_negative_feedback_hide_all_clicks'] = Arr::get($change, 'post_negative_feedback_hide_all_clicks');
        $metrics['post_negative_feedback_report_spam_clicks'] => Arr::get($change, 'post_negative_feedback_report_spam_clicks');
        $metrics['post_negative_feedback_unlike_page_clicks'] => Arr::get($change, 'post_negative_feedback_unlike_page_clicks');

        // Story metrics
        $metrics['story_exits'] = Arr::get($change, 'story_exits');
        $metrics['story_impressions'] = Arr::get($change, 'story_impressions');
        $metrics['story_replies'] = Arr::get($change, 'story_replies');
        $metrics['story_taps_forward'] = Arr::get($change, 'story_taps_forward');
        $metrics['story_taps_back'] = Arr::get($change, 'story_taps_back');

        return array_filter($metrics, fn($value) => $value !== null);
    }

    protected function extractPlatformSpecificUserInfo(array $payload): array
    {
        $userInfo = [];
        
        // From messaging events
        $messaging = Arr::get($payload, 'entry.0.messaging.0', []);
        if ($messaging) {
            $userInfo['user_id'] = Arr::get($messaging, 'sender.id');
            $userInfo['user_ref'] = Arr::get($messaging, 'sender.user_ref');
        }

        // From feed changes
        $change = Arr::get($payload, 'entry.0.changes.0.value', []);
        if ($change) {
            $userInfo['actor_id'] = Arr::get($change, 'actor_id');
            $userInfo['sender_id'] = Arr::get($change, 'sender_id');
            $userInfo['from_id'] = Arr::get($change, 'from.id');
            $userInfo['from_name'] = Arr::get($change, 'from.name');
        }

        // From page info
        $page = Arr::get($payload, 'entry.0', []);
        if ($page) {
            $userInfo['page_id'] = Arr::get($page, 'id');
            $userInfo['page_name'] = Arr::get($page, 'name');
        }

        return array_filter($userInfo, fn($value) => $value !== null);
    }

    protected function extractPlatformSpecificContentInfo(array $payload): array
    {
        $contentInfo = [];
        $change = Arr::get($payload, 'entry.0.changes.0.value', []);

        // Post content
        $contentInfo['message'] = Arr::get($change, 'message');
        $contentInfo['story'] = Arr::get($change, 'story');
        $contentInfo['link'] = Arr::get($change, 'link');
        $contentInfo['picture'] = Arr::get($change, 'picture');
        $contentInfo['full_picture'] = Arr::get($change, 'full_picture');
        $contentInfo['source'] = Arr::get($change, 'source');
        $contentInfo['name'] = Arr::get($change, 'name');
        $contentInfo['caption'] = Arr::get($change, 'caption');
        $contentInfo['description'] = Arr::get($change, 'description');
        $contentInfo['icon'] = Arr::get($change, 'icon');
        $contentInfo['type'] = Arr::get($change, 'type');
        $contentInfo['status_type'] = Arr::get($change, 'status_type');
        $contentInfo['object_id'] = Arr::get($change, 'object_id');
        $contentInfo['application'] = Arr::get($change, 'application');
        $contentInfo['created_time'] = Arr::get($change, 'created_time');
        $contentInfo['updated_time'] = Arr::get($change, 'updated_time');
        $contentInfo['is_published'] = Arr::get($change, 'is_published');
        $contentInfo['is_hidden'] = Arr::get($change, 'is_hidden');
        $contentInfo['is_expired'] = Arr::get($change, 'is_expired');
        $contentInfo['permalink_url'] = Arr::get($change, 'permalink_url');

        // Message content
        $messaging = Arr::get($payload, 'entry.0.messaging.0', []);
        if ($messaging) {
            $message = Arr::get($messaging, 'message', []);
            $contentInfo['message_text'] = Arr::get($message, 'text');
            $contentInfo['message_attachments'] = Arr::get($message, 'attachments');
            $contentInfo['message_quick_reply'] = Arr::get($message, 'quick_reply');
            $contentInfo['message_seq'] = Arr::get($message, 'seq');
        }

        // Lead form content
        if (Arr::get($change, 'leadgen_id')) {
            $contentInfo['leadgen_id'] = Arr::get($change, 'leadgen_id');
            $contentInfo['adgroup_id'] = Arr::get($change, 'adgroup_id');
            $contentInfo['ad_id'] = Arr::get($change, 'ad_id');
            $contentInfo['form_id'] = Arr::get($change, 'form_id');
            $contentInfo['campaign_id'] = Arr::get($change, 'campaign_id');
            $contentInfo['leadgen_data'] = Arr::get($change, 'field_data');
        }

        return array_filter($contentInfo, fn($value) => $value !== null);
    }
}