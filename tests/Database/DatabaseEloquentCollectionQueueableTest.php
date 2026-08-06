<?php

namespace Illuminate\Tests\Database;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use JMac\Testing\TestDouble;
use PHPUnit\Framework\TestCase;

class DatabaseEloquentCollectionQueueableTest extends TestCase
{
    use VerifiesDoubles;

    public function testSerializesPivotsEntitiesId()
    {
        $spy = TestDouble::for(Pivot::class);

        $c = new Collection([$spy]);

        $c->getQueueableIds();

        $spy->received('getQueueableId')
            ->times(1);
    }

    public function testSerializesModelEntitiesById()
    {
        $spy = TestDouble::for(Model::class);

        $c = new Collection([$spy]);

        $c->getQueueableIds();

        $spy->received('getQueueableId')
            ->times(1);
    }

    /**
     * @throws \Exception
     */
    public function testJsonSerializationOfCollectionQueueableIdsWorks()
    {
        // When the ID of a Model is binary instead of int or string, the Collection
        // serialization + JSON encoding breaks because of UTF-8 issues. Encoding
        // of a QueueableCollection must favor QueueableEntity::queueableId().
        $mock = TestDouble::for(Model::class);
        $mock->allows('getKey')->returns(random_bytes(10));
        $mock->allows('getQueueableId')->returns('mocked');

        $c = new Collection([$mock]);

        $payload = [
            'ids' => $c->getQueueableIds(),
        ];

        $this->assertNotFalse(
            json_encode($payload),
            'EloquentCollection is not using the QueueableEntity::getQueueableId() method.'
        );
    }
}
