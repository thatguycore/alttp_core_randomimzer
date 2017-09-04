<?php namespace ALttP;

use ALttP\Support\ItemCollection;
use ALttP\Support\LocationCollection;
use Closure;
use Log;

/**
 * This is the container for all the regions and locations one can find items in the game.
 */
class World {
	protected $difficulty;
	protected $variation;
	protected $logic;
	protected $goal;
	protected $regions = [];
	protected $locations;
	protected $win_condition;
	protected $collectable_locations;

	/**
	 * Create a new world and initialize all of the Regions within it
	 *
	 * @param string $difficulty difficulty from config to apply to randomization
	 * @param string $logic Ruleset to use when deciding if Locations can be reached
	 * @param string $goal Goal of the game
	 * @param string $variation modifications to difficulty
	 *
	 * @return void
	 */
	public function __construct($difficulty = 'normal', $logic = 'NoMajorGlitches', $goal = 'ganon', $variation = 'none') {
		$this->difficulty = $difficulty;
		$this->variation = $variation;
		$this->logic = $logic;
		$this->goal = $goal;

		$this->regions = [
			'Light World' => new Region\LightWorld($this),
			'Escape' => new Region\HyruleCastleEscape($this),
			'Eastern Palace' => new Region\EasternPalace($this),
			'Desert Palace' => new Region\DesertPalace($this),
			'West Death Mountain' => new Region\DeathMountain\West($this),
			'East Death Mountain' => new Region\DeathMountain\East($this),
			'Tower of Hera' => new Region\TowerOfHera($this),
			'Hyrule Castle Tower' => new Region\HyruleCastleTower($this),
			'East Dark World Death Mountain' => new Region\DarkWorld\DeathMountain\East($this),
			'West Dark World Death Mountain' => new Region\DarkWorld\DeathMountain\West($this),
			'North East Dark World' => new Region\DarkWorld\NorthEast($this),
			'North West Dark World' => new Region\DarkWorld\NorthWest($this),
			'South Dark World' => new Region\DarkWorld\South($this),
			'Mire' => new Region\DarkWorld\Mire($this),
			'Palace of Darkness' => new Region\PalaceOfDarkness($this),
			'Swamp Palace' => new Region\SwampPalace($this),
			'Skull Woods' => new Region\SkullWoods($this),
			'Thieves Town' => new Region\ThievesTown($this),
			'Ice Palace' => new Region\IcePalace($this),
			'Misery Mire' => new Region\MiseryMire($this),
			'Turtle Rock' => new Region\TurtleRock($this),
			'Ganons Tower' => new Region\GanonsTower($this),
			'Medallions' => new Region\Medallions($this),
			'Fountains' => new Region\Fountains($this),
		];

		$this->locations = new LocationCollection;

		// Initialize the Logic and Prizes for each Region that has them and fill our LocationsCollection
		foreach ($this->regions as $name => $region) {
			$region->init($logic);
			$this->locations = $this->locations->merge($region->getLocations());
		}

		switch ($this->logic) {
			case 'MajorGlitches':
				$this->win_condition = function($collected_items) {
					if ($this->goal == 'dungeons') {
						if (!$collected_items->has('PendantOfCourage')
							|| !$collected_items->has('PendantOfWisdom')
							|| !$collected_items->has('PendantOfPower')
							|| !$collected_items->has('DefeatAgahnim')) {
							return false;
						}
					}

					return ($collected_items->has('MoonPearl') || $collected_items->hasABottle())
						&& $collected_items->canLightTorches()
						&& (config('game-mode') == 'swordless' || $collected_items->hasUpgradedSword())
						&& (config('game-mode') != 'swordless' || ($collected_items->has('BowAndSilverArrows')
								|| ($collected_items->has('SilverArrowUpgrade')
									&& ($collected_items->has('Bow') || $collected_items->has('BowAndArrows')))))
						&& $collected_items->has('PegasusBoots')
						&& $collected_items->has('Crystal1')
						&& $collected_items->has('Crystal2')
						&& $collected_items->has('Crystal3')
						&& $collected_items->has('Crystal4')
						&& $collected_items->has('Crystal5')
						&& $collected_items->has('Crystal6')
						&& $collected_items->has('Crystal7')
						&& $collected_items->has('DefeatGanon');
				};
				break;
			case 'OverworldGlitches':
			case 'NoMajorGlitches':
			default:
				if ($this->config('rom.HardMode', 0) > 0) {
					$this->win_condition = function($collected_items) {
						if ($this->goal == 'dungeons') {
							if (!$collected_items->has('PendantOfCourage')
								|| !$collected_items->has('PendantOfWisdom')
								|| !$collected_items->has('PendantOfPower')
								|| !$collected_items->has('DefeatAgahnim')) {
								return false;
							}
						}

						return (config('game-mode') == 'swordless' || $collected_items->hasUpgradedSword())
							&& (config('game-mode') != 'swordless' || ($collected_items->has('BowAndSilverArrows')
									|| ($collected_items->has('SilverArrowUpgrade')
										&& ($collected_items->has('Bow') || $collected_items->has('BowAndArrows')))))
							&& $collected_items->canLightTorches()
							&& $this->getLocation("[dungeon-A2-6F] Ganon's Tower - Moldorm room")->canAccess($collected_items);
					};
					break;
				}

				$this->win_condition = function($collected_items) {
					if ($this->goal == 'dungeons') {
						if (!$collected_items->has('PendantOfCourage')
							|| !$collected_items->has('PendantOfWisdom')
							|| !$collected_items->has('PendantOfPower')
							|| !$collected_items->has('Crystal1')
							|| !$collected_items->has('Crystal2')
							|| !$collected_items->has('Crystal3')
							|| !$collected_items->has('Crystal4')
							|| !$collected_items->has('Crystal5')
							|| !$collected_items->has('Crystal6')
							|| !$collected_items->has('Crystal7')
							|| !$collected_items->has('DefeatAgahnim')
							|| !$collected_items->has('DefeatAgahnim2')
							|| !$collected_items->has('DefeatGanon')) {
							return false;
						}
					}

					return $collected_items->has('DefeatGanon')
						&& $collected_items->has('Crystal1')
						&& $collected_items->has('Crystal2')
						&& $collected_items->has('Crystal3')
						&& $collected_items->has('Crystal4')
						&& $collected_items->has('Crystal5')
						&& $collected_items->has('Crystal6')
						&& $collected_items->has('Crystal7');
				};
				break;
		}

		if ($this->difficulty == 'custom') {
			$this->win_condition = function($collected_items) {
				if ($this->goal == 'dungeons') {
					if (!$collected_items->has('PendantOfCourage')
						|| !$collected_items->has('PendantOfWisdom')
						|| !$collected_items->has('PendantOfPower')
						|| !$collected_items->has('DefeatAgahnim')) {
						return false;
					}
				}

				return (config('game-mode') == 'swordless' || $collected_items->hasUpgradedSword())
					&& (config('game-mode') != 'swordless' || ($collected_items->has('BowAndSilverArrows')
							|| ($collected_items->has('SilverArrowUpgrade')
								&& ($collected_items->has('Bow') || $collected_items->has('BowAndArrows')))))
					&& $collected_items->canLightTorches()
					&& $this->getLocation("[dungeon-A2-6F] Ganon's Tower - Moldorm room")->canAccess($collected_items);
			};
		}

		if ($this->goal == 'pedestal') {
			$this->win_condition = function($collected_items) {
				return $this->getLocation("Altar")->canAccess($collected_items);
			};
		}

		if ($this->goal == 'triforce-hunt') {
			$this->win_condition = function($collected_items) {
				return $collected_items->has('TriforcePiece', $this->config('item.Goal.Required'));
			};
		}
	}

	/**
	 * Set the world to the vanilla game.
	 *
	 * @return $this
	 */
	public function setVanilla() {
		foreach ($this->regions as $name => $region) {
			$region->setVanilla();
		}

		return $this;
	}

	/**
	 * Get a copy of this world with items in locations.
	 *
	 * @return static
	 */
	public function copy() {
		$copy = new static($this->difficulty, $this->logic, $this->goal, $this->variation);
		foreach ($this->locations as $name => $location) {
			$copy->locations[$name]->setItem($location->getItem());
		}

		return $copy;
	}

	/**
	 * Create world based on ROM file we read. Might only function with newer v7+ ROMs.
	 * TODO: is this better here or on the Rom object?
	 *
	 * @param Rom $rom Rom object to read from.
	 *
	 * @return $this
	 */
	public function modelFromRom(Rom $rom) {
		foreach ($this->locations as $location) {
			try {
				$location->readItem($rom);
			} catch (\Exception $e) {
				continue;
			}
		}
		return $this;
	}

	/**
	 * Return an array of Locations to collect all Advancement Items in the game in order. This works by cloning the
	 * current world (new Locations and all). Then it groups the locations into collection spheres (all reachable
	 * locations based on the items in the previous sphere). It then attempts to remove each item (starting from the
	 * outer most sphere [latest game locations]), checking the win condition after each removal. If the removed item
	 * makes it impossile to achieve the win condition, it is placed back at the location (and marked as a required
	 * location). If the item is safe to remove, we then take all the items out of the higher spheres and see if we can
	 * still access them with the items available in the lower spheres. If we cannot reach a required item from a higher
	 * sphere we put it back (and mark the location as required). We repeat this process until all spheres have been
	 * pruned. We then take that list of locations with items and run a playthrough of them so we know collection order.
	 *
	 * @param bool $walkthrough include the play order
	 *
	 * @return array
	 */
	public function getPlayThrough($walkthrough = true) {
		$shadow_world = $this->copy();
		$junk_items = [
			Item::get('BlueShield'),
			Item::get('ProgressiveArmor'),
			Item::get('BlueMail'),
			Item::get('Boomerang'),
			Item::get('MirrorShield'),
			Item::get('PieceOfHeart'),
			Item::get('HeartContainer'),
			Item::get('BossHeartContainer'),
			Item::get('RedBoomerang'),
			Item::get('RedShield'),
			Item::get('RedMail'),
			Item::get('BombUpgrade5'),
			Item::get('BombUpgrade10'),
			Item::get('BombUpgrade50'),
			Item::get('ArrowUpgrade5'),
			Item::get('ArrowUpgrade10'),
			Item::get('ArrowUpgrade70'),
			Item::get('Arrow'),
			Item::get('TenArrows'),
			Item::get('Bomb'),
			Item::get('ThreeBombs'),
			Item::get('OneRupee'),
			Item::get('FiveRupees'),
			Item::get('TwentyRupees'),
			Item::get('FiftyRupees'),
			Item::get('OneHundredRupees'),
			Item::get('ThreeHundredRupees'),
			Item::get('Heart'),
			Item::get('Rupoor'),
		];

		$location_sphere = $shadow_world->getLocationSpheres();
		$collectable_locations = new LocationCollection(array_flatten(array_map(function($collection) {
			return $collection->values();
		}, $location_sphere)));
		$required_locations = new LocationCollection;
		$required_locations_sphere = [];
		$reverse_location_sphere = array_reverse($location_sphere, true);
		foreach ($reverse_location_sphere as $sphere_level => $sphere) {
			Log::debug("playthrough SPHERE: $sphere_level");
			foreach ($sphere as $location) {
				Log::debug(sprintf("playthrough Check: %s :: %s", $location->getName(),
					$location->getItem() ? $location->getItem()->getNiceName() : 'Nothing'));
				// pull item out (we have to pull keys as well :( as they are used in calcs for big keys see DP)
				$pulled_item = $location->getItem();
				if ($pulled_item === null) {
					continue;
				}
				$location->setItem();
				if ($pulled_item instanceof Item\Map
					|| $pulled_item instanceof Item\Compass
					|| in_array($pulled_item, $junk_items)) {
					continue;
				}

				if (!$shadow_world->getWinCondition()($collectable_locations->getItems())) {
					// put item back
					$location->setItem($this->locations[$location->getName()]->getItem());
					$required_locations->addItem($location);
					$required_locations_sphere[$sphere_level][] = $location;
					Log::debug(sprintf("playthrough Keep: %s :: %s", $location->getName(), $location->getItem()->getNiceName()));
					continue;
				}

				// Itterate all spheres bubbling up -_-
				foreach (array_reverse(array_keys($required_locations_sphere)) as $check_sphere) {
					// don't check the current sphere (thats a waste of time).
					if ($check_sphere == $sphere_level || $required_locations->has($location->getName())) {
						continue;
					}

					// remove all higher sphere items from their locations
					foreach ($required_locations_sphere as $higher_sphere => $higher_locations) {
						if ($higher_sphere < $check_sphere) {
							continue;
						}
						foreach ($higher_locations as $higher_location) {
							$higher_location->setItem();
						}
					}

					// test access of items in the outer sphere
					foreach ($required_locations_sphere as $higher_sphere => $higher_locations) {
						if ($higher_sphere != $check_sphere) {
							continue;
						}
						foreach ($higher_locations as $higher_location) {
							// remove the item we are trying to get
							$temp_pull = $higher_location->getItem();
							$higher_location->setItem();
							$current_items = $collectable_locations->getItems();

							if (!$higher_location->canAccess($current_items, $this->getLocations())) {
								// put item back
								$location->setItem($this->locations[$location->getName()]->getItem());
								Log::debug(sprintf("playthrough Higher Location: %s :: %s", $higher_location->getName(),
									$this->locations[$higher_location->getName()]->getItem()->getNiceName()));
								$required_locations->addItem($location);
								$required_locations_sphere[$sphere_level][] = $location;
								Log::debug(sprintf("playthrough Readd: %s :: %s", $location->getName(),
									$location->getItem()->getNiceName()));
								break 2;
							}
							$higher_location->setItem($temp_pull);
						}
					}
					// put all higher items back
					foreach ($required_locations as $higher_location) {
						$higher_location->setItem($this->locations[$higher_location->getName()]->getItem());
					}
				}
			}
		}

		foreach ($required_locations as $higher_location) {
			Log::debug(sprintf("playthrough REQ: %s :: %s", $higher_location->getName(),
				$this->locations[$higher_location->getName()]->getItem()->getNiceName()));
		}
		if (!$walkthrough) {
			return $required_locations->values();
		}

		// RUN PLAYTHROUGH of locations found above
		$my_items = new ItemCollection;
		$location_order = [];
		$location_round = [];
		$longest_item_chain = 1;
		do {
			// make sure we had something before going to the next round
			if (!empty($location_round[$longest_item_chain])) {
				$longest_item_chain++;				
			}
			$location_round[$longest_item_chain] = [];
			$available_locations = $shadow_world->getCollectableLocations()->filter(function($location) use ($my_items) {
				return $location->canAccess($my_items, $this->getLocations());
			});

			$found_items = $available_locations->getItems();

			$available_locations->each(function($location) use (&$location_order, &$location_round, $longest_item_chain, $junk_items) {
				$item = $location->getItem();
				if (in_array($location, $location_order)
						|| !$location->hasItem()
						|| $item instanceof Item\Key) {
					return;
				}
				Log::debug(sprintf("Pushing: %s from %s", $item->getNiceName(), $location->getName()));
				array_push($location_order, $location);
				array_push($location_round[$longest_item_chain], $location);
			});

			$new_items = $found_items->diff($my_items);
			$my_items = $found_items;
		} while ($new_items->count() > 0);

		$ret = ['longest_item_chain' => count($location_round)];
		foreach ($location_round as $round => $locations) {
			if (!count($locations)) {
				$ret['longest_item_chain']--;
			}
			foreach ($locations as $location) {
				$ret[$round][$location->getRegion()->getName()][$location->getName()] = $location->getItem()->getNiceName();
			}
		}

		$ret['regions_visited'] = array_reduce($ret, function($carry, $item) {
			return (is_array($item)) ? $carry + count($item) : $carry;
		});

		return $ret;
	}

	/**
	 * Get the function that determines the win condition for this world.
	 *
	 * @return Closure
	 */
	public function getWinCondition() {
		return $this->win_condition;
	}

	/**
	 * perhaps allow winconditions to be added.
	 *
	 * @param ItemCollection $items
	 *
	 * @return bool
	 */
	public function checkWinCondition(ItemCollection $items) {
		if (is_array($this->win_condition)) {
			foreach ($this->win_condition as $condition) {
				if (!call_user_func($condition, $items)) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Get config value based on the currently set rules
	 *
	 * @param string $key dot notation key of config
	 * @param mixed|null $default value to return if $key is not found
	 *
	 * @return mixed
	 */
	public function config(string $key, $default = null) {
		return config("alttp.{$this->difficulty}.variations.{$this->variation}.$key", config("alttp.{$this->difficulty}.$key", $default));
	}

	/**
	 * Get a region by Key name
	 *
	 * @param string $name Name of region to return
	 *
	 * @return Region|null
	 */
	public function getRegion(string $name) {
		return $this->regions[$name] ?? null;
	}

	/**
	 * Get all the Regions in this world
	 *
	 * @return array
	 */
	public function getRegions() {
		return $this->regions;
	}

	/**
	 * Get all the Locations in all Regions in this world
	 *
	 * @return LocationCollection
	 */
	public function getLocations() {
		return $this->locations;
	}

	/**
	 * Get Locations considered collectable. I.E. can contain items that Link can have.
	 * This is cached for faster retrevial
	 *
	 * @return LocationCollection
	 */
	public function getCollectableLocations() {
		if (!$this->collectable_locations) {
			$this->collectable_locations = $this->locations->filter(function($location) {
				return !is_a($location, Location\Medallion::class)
					&& !is_a($location, Location\Fountain::class);
			});
		}

		return $this->collectable_locations;
	}

	/**
	 * Collect the items in the world, you may pass in a set of pre-collected items.
	 *
	 * @param ItemCollection $collected precollected items for consideration in out collecting
	 *
	 * @return ItemCollection
	 */
	public function collectItems(ItemCollection $collected = null) {
		$my_items = $collected ?? new ItemCollection;
		$available_locations = $this->getCollectableLocations()->filter(function($location) {
			return $location->hasItem();
		});

		do {
			$search_locations = $available_locations->filter(function($location) use ($my_items) {
				return $location->canAccess($my_items);
			});

			$available_locations = $available_locations->diff($search_locations);

			$found_items = $search_locations->getItems();
			$my_items = $found_items->merge($my_items);
		} while ($found_items->count() > 0);

		return $my_items;
	}

	/**
	 * Determine the spheres that locations are in based on the items in the world
	 *
	 * @return array
	 */
	public function getLocationSpheres() {
		$sphere = 0;
		$location_sphere = [];
		$my_items = new ItemCollection;
		$found_locations = new LocationCollection;
		do {
			$sphere++;
			$available_locations = $this->locations->filter(function($location) use ($my_items) {
				return !is_a($location, Location\Medallion::class)
					&& !is_a($location, Location\Fountain::class)
					&& $location->canAccess($my_items);
			});
			$location_sphere[$sphere] = $available_locations->diff($found_locations);

			$found_items = $available_locations->getItems();
			$found_locations = $available_locations;

			$new_items = $found_items->diff($my_items);
			$my_items = $found_items;
		} while ($new_items->count() > 0);

		return $location_sphere;
	}

	/**
	 * Get Location in this world by name
	 *
	 * @param string $name name of the Location
	 *
	 * @return Location
	 */
	public function getLocation(string $name) {
		return $this->locations[$name];
	}

	/**
	 * Get all the Locations in this Region that do not have an Item assigned
	 *
	 * @return Support\LocationCollection
	 */
	public function getEmptyLocations() {
		return $this->locations->filter(function($location) {
			return !$location->hasItem();
		});
	}

	/**
	 * Get all the Locations that contain the requested Item
	 *
	 * @param Item|null $item item we are looking for
	 *
	 * @return LocationCollection
	 */
	public function getLocationsWithItem(Item $item = null) {
		return $this->locations->locationsWithItem($item);
	}

	/**
	 * Get all the Regions that contain the requested Item
	 *
	 * @param Item|null $item item we are looking for
	 *
	 * @return array
	 */
	public function getRegionsWithItem(Item $item = null) {
		return $this->getLocationsWithItem($item)->getRegions();
	}
}
