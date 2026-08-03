<?php

namespace Illuminate\Tests\Pagination;

use JMac\Testing\TestDouble;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\AbstractPaginator;
use PHPUnit\Framework\TestCase;

class PaginatorLoadMorphCountTest extends TestCase
{
    public function testCollectionLoadMorphCountCanChainOnThePaginator()
    {
        $relations = [
            'App\\User' => 'photos',
            'App\\Company' => ['employees', 'calendars'],
        ];

        $items = TestDouble::for(Collection::class);
        $items->shouldReceive('loadMorphCount')->once()->with('parentable', $relations);

        $p = (new class extends AbstractPaginator {
        })->setCollection($items);

        $this->assertSame($p, $p->loadMorphCount('parentable', $relations));
    }
}
