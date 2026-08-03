<?php

namespace Illuminate\Tests\Database;

use JMac\Testing\TestDouble;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;
use stdClass;

class DatabaseSoftDeletingTraitTest extends TestCase
{
    public function testDeleteSetsSoftDeletedColumn()
    {
        $model = TestDouble::for(DatabaseSoftDeletingTraitStub::class);
        $model->passthru();
        $model->allows('newModelQuery')->returns($query = TestDouble::for(stdClass::class));
        $query->expects('where')->with('id', '=', 1)->returns($query);
        $query->expects('update')->with([
            'deleted_at' => 'date-time',
            'updated_at' => 'date-time',
        ]);
        $model->expects('syncOriginalAttributes')->with([
            'deleted_at',
            'updated_at',
        ]);
        $model->expects('usesTimestamps')->returns(true);
        $model->delete();

        $this->assertInstanceOf(Carbon::class, $model->deleted_at);
    }

    public function testRestore()
    {
        $model = TestDouble::for(DatabaseSoftDeletingTraitStub::class);
        $model->passthru();
        $model->allows('fireModelEvent')->with('restoring')->returns(true);
        $model->expects('save');
        $model->allows('fireModelEvent')->with('restored', false)->returns(true);

        $model->restore();

        $this->assertNull($model->deleted_at);
    }

    public function testRestoreCancel()
    {
        $model = TestDouble::for(DatabaseSoftDeletingTraitStub::class);
        $model->passthru();
        $model->allows('fireModelEvent')->with('restoring')->returns(false);
        $model->expects('save')->never();

        $this->assertFalse($model->restore());
    }
}

class DatabaseSoftDeletingTraitStub
{
    use SoftDeletes;

    public $deleted_at;
    public $updated_at;
    public $timestamps = true;
    public $exists = false;

    public function newQuery()
    {
        //
    }

    public function getKey()
    {
        return 1;
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function save()
    {
        //
    }

    public function delete()
    {
        return $this->performDeleteOnModel();
    }

    public function fireModelEvent()
    {
        //
    }

    public function freshTimestamp()
    {
        return Carbon::now();
    }

    public function fromDateTime()
    {
        return 'date-time';
    }

    public function getUpdatedAtColumn()
    {
        return defined('static::UPDATED_AT') ? static::UPDATED_AT : 'updated_at';
    }

    public function setKeysForSaveQuery($query)
    {
        $query->where($this->getKeyName(), '=', $this->getKeyForSaveQuery());

        return $query;
    }

    protected function getKeyForSaveQuery()
    {
        return 1;
    }
}
