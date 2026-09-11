<?php

namespace Illuminate\Tests\Notifications;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Tests\Notifications\Fixtures\Models\NotifiableUser;
use Illuminate\Tests\TestCase;
use JMac\Testing\Double;
use JMac\Testing\Matching\Argument;

class NotificationDatabaseChannelTest extends TestCase
{
    public function testDatabaseChannelCreatesDatabaseRecordWithProperData()
    {
        $notification = new NotificationDatabaseChannelTestNotification;
        $notification->id = 1;
        $notifiable = Double::for(NotifiableUser::class);

        $route = Double::for(MorphMany::class);
        $route->expects('create')->with([
            'id' => 1,
            'type' => get_class($notification),
            'data' => ['invoice_id' => 1],
            'read_at' => null,
        ]);
        $notifiable->allows('routeNotificationFor')->returns($route);

        $channel = new DatabaseChannel;
        $channel->send($notifiable, $notification);
    }

    public function testCorrectPayloadIsSentToDatabase()
    {
        $notification = new NotificationDatabaseChannelTestNotification;
        $notification->id = 1;
        $notifiable = Double::for(NotifiableUser::class);

        $route = Double::for(MorphMany::class);
        $route->expects('create')->with([
            'id' => 1,
            'type' => get_class($notification),
            'data' => ['invoice_id' => 1],
            'read_at' => null,
            'something' => 'else',
        ]);
        $notifiable->allows('routeNotificationFor')->returns($route);

        $channel = new ExtendedDatabaseChannel;
        $channel->send($notifiable, $notification);
    }

    public function testCustomizeTypeIsSentToDatabase()
    {
        $notification = new NotificationDatabaseChannelCustomizeTypeTestNotification;
        $notification->id = 1;
        $notifiable = Double::for(NotifiableUser::class);

        $expectedReadAt = Carbon::now()->toDateTimeString();
        $route = Double::for(MorphMany::class);
        $route->expects('create')->with(Argument::satisfies(function ($attributes) use ($expectedReadAt) {
            return $attributes['id'] === 1
                && $attributes['type'] === 'MONTHLY'
                && $attributes['data'] === ['invoice_id' => 1]
                && (string) $attributes['read_at'] === $expectedReadAt
                && $attributes['something'] === 'else';
        }));
        $notifiable->allows('routeNotificationFor')->returns($route);

        $channel = new ExtendedDatabaseChannel;
        $channel->send($notifiable, $notification);
    }
}

class NotificationDatabaseChannelTestNotification extends Notification
{
    public function toDatabase($notifiable)
    {
        return new DatabaseMessage(['invoice_id' => 1]);
    }
}

class NotificationDatabaseChannelCustomizeTypeTestNotification extends Notification
{
    public function toDatabase($notifiable)
    {
        return new DatabaseMessage(['invoice_id' => 1]);
    }

    public function databaseType()
    {
        return 'MONTHLY';
    }

    public function initialDatabaseReadAtValue()
    {
        return Carbon::now();
    }
}

class ExtendedDatabaseChannel extends DatabaseChannel
{
    protected function buildPayload($notifiable, Notification $notification)
    {
        return array_merge(parent::buildPayload($notifiable, $notification), [
            'something' => 'else',
        ]);
    }
}
