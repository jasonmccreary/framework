<?php

namespace Illuminate\Tests\Pagination;

use JMac\Testing\TestDouble;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\AbstractCursorPaginator;
use PHPUnit\Framework\TestCase;

class CursorPaginatorLoadMorphTest extends TestCase
{
    public function testCollectionLoadMorphCanChainOnThePaginator()
    {
        $relations = [
            'App\\User' => 'photos',
            'App\\Company' => ['employees', 'calendars'],
        ];

        $items = TestDouble::for(Collection::class);
        $items->expects('loadMorph')->with('parentable', $relations);

        $p = (new class extends AbstractCursorPaginator {
        })->setCollection($items);

        $this->assertSame($p, $p->loadMorph('parentable', $relations));
    }
}
