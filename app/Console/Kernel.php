<?php namespace ALttP\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel {
	protected $commands = [
		Commands\Distribution::class,
		Commands\JsonToCsv::class,
		Commands\LogicArray::class,
		Commands\Randomize::class,
		Commands\SpoilerFromRom::class,
		Commands\UpdateBaseJson::class,
		Commands\UpdateBuildRecord::class,
		Commands\GenerateStats::class,
	];

	protected function commands() {
		require base_path('routes/console.php');
	}
}
