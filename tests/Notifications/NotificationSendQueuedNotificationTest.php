<?php

namespace Illuminate\Tests\Notifications;

use Illuminate\Contracts\Database\ModelIdentifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Collection;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use JMac\Testing\Matching\Argument;
use JMac\Testing\TestDouble;
use PHPUnit\Framework\TestCase;

class NotificationSendQueuedNotificationTest extends TestCase
{
    use VerifiesDoubles;

    public function testNotificationsCanBeSent()
    {
        $notification = new TestNotification;
        $job = new SendQueuedNotifications('notifiables', $notification);
        $manager = TestDouble::for(ChannelManager::class);
        $manager->expects('sendNow')->with(
            Argument::satisfies(fn ($notifiables) => $notifiables instanceof Collection && $notifiables->toArray() === ['notifiables']),
            Argument::type(TestNotification::class),
            Argument::same(null),
        );
        $job->handle($manager);
    }

    public function testSerializationOfNotifiableModel()
    {
        $identifier = new ModelIdentifier(NotifiableUser::class, [null], [], null);
        $serializedIdentifier = serialize($identifier);

        $job = new SendQueuedNotifications(new NotifiableUser, new TestNotification);
        $serialized = serialize($job);

        $this->assertStringContainsString($serializedIdentifier, $serialized);
    }

    public function testSerializationOfNormalNotifiable()
    {
        $notifiable = new AnonymousNotifiable;
        $serializedNotifiable = serialize($notifiable);

        $job = new SendQueuedNotifications($notifiable, new TestNotification);
        $serialized = serialize($job);

        $this->assertStringContainsString($serializedNotifiable, $serialized);
    }

    public function testNotificationCanSetMaxExceptions()
    {
        $notifiable = new NotifiableUser;
        $notification = new class
        {
            public $maxExceptions = 23;
        };

        $job = new SendQueuedNotifications($notifiable, $notification);

        $this->assertEquals(23, $job->maxExceptions);
    }
}

class NotifiableUser extends Model
{
    use Notifiable;

    public $table = 'users';
    public $timestamps = false;
}

class TestNotification extends Notification
{
}
