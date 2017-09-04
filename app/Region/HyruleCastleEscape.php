<?php namespace ALttP\Region;

use ALttP\Item;
use ALttP\Location;
use ALttP\Region;
use ALttP\Support\LocationCollection;
use ALttP\World;

/**
 * Hyrule Castle Escape Region and it's Locations contained within
 */
class HyruleCastleEscape extends Region {
	protected $name = 'Hyrule Castle';

	protected $region_items = [
		'BigKey',
		'BigKeyH2',
		'Compass',
		'CompassH2',
		'Key',
		'KeyH2',
		'Map',
		'MapH2',
	];

	/**
	 * Create a new Hyrule Castle Escape Region and initalize it's locations
	 *
	 * @param World $world World this Region is part of
	 *
	 * @return void
	 */
	public function __construct(World $world) {
		parent::__construct($world);

		$this->locations = new LocationCollection([
			new Location\Chest("[dungeon-C-1F] Sanctuary", 0xEA79, null, $this),
			new Location\Chest("[dungeon-C-B1] Escape - final basement room [left chest]", 0xEB5D, null, $this),
			new Location\Chest("[dungeon-C-B1] Escape - final basement room [middle chest]", 0xEB60, null, $this),
			new Location\Chest("[dungeon-C-B1] Escape - final basement room [right chest]", 0xEB63, null, $this),
			new Location\Chest("[dungeon-C-B1] Escape - first B1 room", 0xE96E, null, $this),
			new Location\Chest("[dungeon-C-B1] Hyrule Castle - boomerang room", 0xE974, null, $this),
			new Location\Chest("[dungeon-C-B1] Hyrule Castle - map room", 0xEB0C, null, $this),
			new Location\Chest("[dungeon-C-B3] Hyrule Castle - next to Zelda", 0xEB09, null, $this),
		]);
	}

	/**
	 * Set Locations to have Items like the vanilla game.
	 *
	 * @return $this
	 */
	public function setVanilla() {
		$this->locations["[dungeon-C-1F] Sanctuary"]->setItem(Item::get('HeartContainer'));
		$this->locations["[dungeon-C-B1] Escape - final basement room [left chest]"]->setItem(Item::get('ThreeBombs'));
		$this->locations["[dungeon-C-B1] Escape - final basement room [middle chest]"]->setItem(Item::get('ThreeHundredRupees'));
		$this->locations["[dungeon-C-B1] Escape - final basement room [right chest]"]->setItem(Item::get('TenArrows'));
		$this->locations["[dungeon-C-B1] Escape - first B1 room"]->setItem(Item::get('KeyH2'));
		$this->locations["[dungeon-C-B1] Hyrule Castle - boomerang room"]->setItem(Item::get('Boomerang'));
		$this->locations["[dungeon-C-B1] Hyrule Castle - map room"]->setItem(Item::get('MapH2'));
		$this->locations["[dungeon-C-B3] Hyrule Castle - next to Zelda"]->setItem(Item::get('Lamp'));

		return $this;
	}

	/**
	 * Initalize the requirements for Entry and Completetion of the Region as well as access to all Locations contained
	 * within for No Major Glitches
	 *
	 * @return $this
	 */
	public function initNoMajorGlitches() {
		$this->locations["[dungeon-C-1F] Sanctuary"]->setFillRules(function($item, $locations, $items) {
			if (!in_array(config('game-mode'), ['open', 'swordless'])) {
				return $item != Item::get('KeyH2');
			}

			return true;
		});

		$this->locations["[dungeon-C-B1] Escape - final basement room [left chest]"]->setRequirements(function($locations, $items) {
			if (in_array(config('game-mode'), ['open', 'swordless'])) {
				return $items->canLiftRocks() || ($items->has('Lamp') && $items->has('KeyH2'));
			}

			return true;
		})->setFillRules(function($item, $locations, $items) {
			if (!in_array(config('game-mode'), ['open', 'swordless'])) {
				return $item != Item::get('KeyH2');
			}

			return true;
		});

		$this->locations["[dungeon-C-B1] Escape - final basement room [middle chest]"]->setRequirements(function($locations, $items) {
			if (in_array(config('game-mode'), ['open', 'swordless'])) {
				return $items->canLiftRocks() || ($items->has('Lamp') && $items->has('KeyH2'));
			}

			return true;
		})->setFillRules(function($item, $locations, $items) {
			if (!in_array(config('game-mode'), ['open', 'swordless'])) {
				return $item != Item::get('KeyH2');
			}

			return true;
		});

		$this->locations["[dungeon-C-B1] Escape - final basement room [right chest]"]->setRequirements(function($locations, $items) {
			if (in_array(config('game-mode'), ['open', 'swordless'])) {
				return $items->canLiftRocks() || ($items->has('Lamp') && $items->has('KeyH2'));
			}

			return true;
		})->setFillRules(function($item, $locations, $items) {
			if (!in_array(config('game-mode'), ['open', 'swordless'])) {
				return $item != Item::get('KeyH2');
			}

			return true;
		});

		$this->locations["[dungeon-C-B1] Escape - first B1 room"]->setRequirements(function($locations, $items) {
			if (in_array(config('game-mode'), ['open', 'swordless'])) {
				return $items->has('Lamp');
			}

			return true;
		});

		$this->locations["[dungeon-C-B1] Hyrule Castle - boomerang room"]->setFillRules(function($item, $locations, $items) {
			if (in_array(config('game-mode'), ['open', 'swordless'])) {
				return $item != Item::get('KeyH2');
			}

			return true;
		});

		$this->locations["[dungeon-C-B3] Hyrule Castle - next to Zelda"]->setFillRules(function($item, $locations, $items) {
			if (in_array(config('game-mode'), ['open', 'swordless'])) {
				return $item != Item::get('KeyH2');
			}

			return true;
		});

		return $this;
	}
}
