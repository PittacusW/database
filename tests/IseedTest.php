<?php

namespace Pittacusw\Database\Tests;

use Illuminate\Support\Composer;
use Mockery as m;
use Pittacusw\Database\Iseed;

class IseedTest extends TestCase
{
    public function testGenerateClassNameBuildsSeederName()
    {
        $iseed = new Iseed($this->files);

        $this->assertSame('PatientAppointmentsTableSeeder', $iseed->generateClassName('patient_appointments'));
    }

    public function testRepackSeedDataConvertsZeroDatesToNull()
    {
        $iseed = new Iseed($this->files);

        $data = collect([
            [
                'id' => 1,
                'created_at' => '0000-00-00 00:00:00',
                'deleted_at' => '0000-00-00',
            ],
        ]);

        $this->assertSame([
            [
                'id' => 1,
                'created_at' => null,
                'deleted_at' => null,
            ],
        ], $iseed->repackSeedData($data));
    }

    public function testPopulateStubChunksInsertStatementsAndCanRemoveIndexes()
    {
        $iseed = new Iseed($this->files);
        $stub = $iseed->readStubFile($iseed->getStubPath().DIRECTORY_SEPARATOR.'seed.stub');

        $output = $iseed->populateStub('UsersTableSeeder', $stub, 'users', [
            ['id' => 1, 'name' => 'One'],
            ['id' => 2, 'name' => 'Two'],
            ['id' => 3, 'name' => 'Three'],
        ], 2, null, null, false);

        $this->assertSame(2, substr_count($output, "DB::table('users')->insert("));
        $this->assertStringContainsString("class UsersTableSeeder extends Seeder", $output);
        $this->assertStringNotContainsString('0 =>', $output);
        $this->assertStringContainsString("Schema::enableForeignKeyConstraints();", $output);
    }

    public function testGenerateSeedWritesSeederAndUpdatesDatabaseSeeder()
    {
        $this->putDatabaseSeeder(<<<'PHP'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        #iseed_start
        #iseed_end
    }
}
PHP
);

        $composer = m::mock(Composer::class, [$this->files, $this->basePath]);
        $composer->shouldReceive('dumpAutoloads')->once();

        $iseed = m::mock(Iseed::class, [$this->files, $composer])->makePartial();
        $iseed->shouldReceive('hasTable')->once()->with('users')->andReturn(true);
        $iseed->shouldReceive('getData')->once()->with('users', 0, [], 'id', 'DESC')->andReturn(collect([
            ['id' => 1, 'name' => 'One'],
        ]));

        $this->useIseed($iseed);

        $result = $iseed->generateSeed('users', null, null, 'testing', 0, 1, [], null, null, true, true, 'id', 'DESC');

        $this->assertTrue($result);
        $this->assertFileExists($this->basePath.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'seeders'.DIRECTORY_SEPARATOR.'UsersTableSeeder.php');
        $this->assertStringContainsString('$this->call(UsersTableSeeder::class);', $this->files->get($this->databaseSeederPath()));
    }

    public function testCleanSectionRemovesGeneratedSeederCallsButKeepsMarkers()
    {
        $this->putDatabaseSeeder(<<<'PHP'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        #iseed_start
        $this->call(UsersTableSeeder::class);
        $this->call(PostsTableSeeder::class);
        #iseed_end
    }
}
PHP
);

        $iseed = new Iseed($this->files);

        $this->assertTrue($iseed->cleanSection());
        $contents = $this->files->get($this->databaseSeederPath());

        $this->assertStringContainsString('#iseed_start', $contents);
        $this->assertStringContainsString('#iseed_end', $contents);
        $this->assertStringNotContainsString('UsersTableSeeder', $contents);
        $this->assertStringNotContainsString('PostsTableSeeder', $contents);
    }

    public function testUpdateDatabaseSeederRunMethodDoesNotDuplicateEntries()
    {
        $this->putDatabaseSeeder(<<<'PHP'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
    }
}
PHP
);

        $iseed = new Iseed($this->files);

        $this->assertTrue($iseed->updateDatabaseSeederRunMethod('UsersTableSeeder'));
        $this->assertTrue($iseed->updateDatabaseSeederRunMethod('UsersTableSeeder'));

        $this->assertSame(1, substr_count($this->files->get($this->databaseSeederPath()), '$this->call(UsersTableSeeder::class);'));
    }

    public function testUpdateDatabaseSeederRunMethodAppendsMultipleEntriesWithinManagedMarkers()
    {
        $this->putDatabaseSeeder(<<<'PHP'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        #iseed_start
        #iseed_end
    }
}
PHP
);

        $iseed = new Iseed($this->files);

        $this->assertTrue($iseed->updateDatabaseSeederRunMethod('UsersTableSeeder'));
        $this->assertTrue($iseed->updateDatabaseSeederRunMethod('PostsTableSeeder'));

        $contents = $this->files->get($this->databaseSeederPath());

        $this->assertStringContainsString('$this->call(UsersTableSeeder::class);', $contents);
        $this->assertStringContainsString('$this->call(PostsTableSeeder::class);', $contents);
    }
}
