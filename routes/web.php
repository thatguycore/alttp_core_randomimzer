<?php

use Illuminate\Http\Request;

Route::get('randomize{r?}', function () {
	return view('randomizer');
});

Route::get('entrance/randomize{r?}', function () {
	return view('entrance_randomizer');
});

Route::get('/', function () {
	return view('about');
});

Route::get('about', function () {
	return view('about');
});

Route::get('game_modes', function () {
	return view('game_modes');
});

Route::get('game_logics', function () {
	return view('game_logics');
});

Route::get('game_difficulties', function () {
	return view('game_difficulties');
});

Route::get('game_variations', function () {
	return view('game_variations');
});

Route::get('game_entrance', function () {
	return view('game_entrance');
});

Route::get('info', function () {
	return redirect('help');
});

Route::get('stuck', function () {
	return view('stuck');
});

Route::get('help', function () {
	return view('help');
});

Route::get('updates', function () {
	return view('updates');
});

Route::get('spoiler_click/{seed_id?}', function() {
	return "Ok";
});

Route::any('hash/{hash}', function(Request $request, $hash) {
	$seed = ALttP\Seed::where('hash', $hash)->first();
	if ($seed) {
		return json_encode([
			'logic' => $seed->logic,
			'difficulty' => $seed->rules,
			'patch' => json_decode($seed->patch),
			'spoiler' => array_except(array_only(json_decode($seed->spoiler, true), ['meta']), ['meta.seed']),
			'hash' => $seed->hash,
		]);
	}
	abort(404);
});

Route::any('entrance/seed/{seed_id?}', function(Request $request, $seed_id = null) {
	$difficulty = $request->input('difficulty', 'normal') ?: 'normal';
	$variation = $request->input('variation', 'none') ?: 'none';

	config(['game-mode' => $request->input('mode', 'standard')]);

	$rom = new ALttP\Rom();
	if ($request->has('heart_speed')) {
		$rom->setHeartBeepSpeed($request->input('heart_speed'));
	}
	if ($request->has('sram_trace')) {
		$rom->setSRAMTrace($request->input('sram_trace') == 'true');
	}
	if ($request->has('debug')) {
		$rom->setDebugMode($request->input('debug') == 'true');
	}

	try {
		$rand = new ALttP\EntranceRandomizer($difficulty, 'noglitches', $request->input('goal', 'ganon'), $variation, $request->input('shuffle', 'full'));
		$rand->makeSeed($seed_id);
		$rand->writeToRom($rom);
		$seed = $rand->getSeed();
		$patch = $rom->getWriteLog();
		$spoiler = $rand->getSpoiler();
		$hash = $rand->saveSeedRecord();
	} catch (Exception $e) {
		return response('Failed', 409);
	}

	if ($request->has('tournament') && $request->input('tournament') == 'true') {
		$rom->setSeedString(str_pad(sprintf("ER TOURNEY %s", $hash), 21, ' '));
		$patch = patch_merge_minify($rom->getWriteLog());
		$rand->updateSeedRecordPatch($patch);
		$spoiler = array_except(array_only($spoiler, ['meta']), ['meta.seed']);
		$seed = $hash;
	}

	return json_encode([
		'seed' => $seed,
		'logic' => $rand->getLogic(),
		'difficulty' => $difficulty,
		'patch' => $patch,
		'spoiler' => $spoiler,
		'hash' => $hash,
	]);
});

Route::any('seed/{seed_id?}', function(Request $request, $seed_id = null) {
	$difficulty = $request->input('difficulty', 'normal') ?: 'normal';
	if ($difficulty == 'custom') {
		config($request->input('data'));
	}
	$variation = $request->input('variation', 'none') ?: 'none';

	config(['game-mode' => $request->input('mode', 'standard')]);

	$rom = new ALttP\Rom();
	if ($request->has('heart_speed')) {
		$rom->setHeartBeepSpeed($request->input('heart_speed'));
	}
	if ($request->has('sram_trace')) {
		$rom->setSRAMTrace($request->input('sram_trace') == 'true');
	}
	if ($request->has('debug')) {
		$rom->setDebugMode($request->input('debug') == 'true');
	}

	if ($request->has('tournament') && $request->input('tournament') == 'true') {
		config([
			"tournament-mode" => true,
		]);
		$rom->setTournamentType('standard');
	} else {
		$rom->setTournamentType('none');
	}

	$seed_id = is_numeric($seed_id) ? $seed_id : abs(crc32($seed_id));

	$rand = new ALttP\Randomizer($difficulty, $request->input('logic', 'NoMajorGlitches'), $request->input('goal', 'ganon'), $variation);
	$rand->makeSeed($seed_id);
	$rand->writeToRom($rom);
	$seed = $rand->getSeed();
	$patch = $rom->getWriteLog();
	$spoiler = $rand->getSpoiler();
	$hash = $rand->saveSeedRecord();

	if ($request->has('tournament') && $request->input('tournament') == 'true') {
		$rom->setSeedString(str_pad(sprintf("VT TOURNEY %s", $hash), 21, ' '));
		$rom->rummageTable();
		$patch = patch_merge_minify($rom->getWriteLog());
		$rand->updateSeedRecordPatch($patch);
		$spoiler = array_except(array_only($spoiler, ['meta']), ['meta.seed']);
		$seed = $hash;
	}

	return json_encode([
		'seed' => $seed,
		'logic' => $rand->getLogic(),
		'difficulty' => $difficulty,
		'patch' => $patch,
		'spoiler' => $spoiler,
		'hash' => $hash,
	]);
});

Route::get('spoiler/{seed_id}', function(Request $request, $seed_id) {
	$difficulty = $request->input('difficulty', 'normal');
	if ($difficulty == 'custom') {
		config($request->input('data'));
	}
	$variation = $request->input('variation', 'none') ?: 'none';

	config(['game-mode' => $request->input('mode', 'standard')]);

	if ($request->has('tournament') && $request->input('tournament') == 'true') {
		config([
			"tournament-mode" => true,
		]);
	}

	$seed_id = is_numeric($seed_id) ? $seed_id : abs(crc32($seed_id));

	$rand = new ALttP\Randomizer($difficulty, $request->input('logic', 'NoMajorGlitches'), $request->input('goal', 'ganon'), $variation);
	$rand->makeSeed($seed_id);
	return json_encode($rand->getSpoiler());
});

Route::get('h/{hash}', function(Request $request, $hash) {
	$seed = ALttP\Seed::where('hash', $hash)->first();
	if ($seed) {
		$build = ALttP\Build::where('build', $seed->build)->first();
		if (!$build) {
			abort(404);
		}
		return view('patch_from_hash', [
			'hash' => $hash,
			'md5' => $build->hash,
			'patch' => $build->patch,
		]);
	}
	abort(404);
});
