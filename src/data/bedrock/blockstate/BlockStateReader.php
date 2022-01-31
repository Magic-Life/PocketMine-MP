<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\data\bedrock\blockstate;

use pocketmine\block\utils\BellAttachmentType;
use pocketmine\block\utils\CoralType;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\utils\SlabType;
use pocketmine\data\bedrock\blockstate\BlockStateValues as Values;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\Tag;
use function get_class;

final class BlockStateReader{

	private CompoundTag $nbt;

	public function __construct(CompoundTag $nbt){
		$this->nbt = $nbt;
	}

	public function getNbt() : CompoundTag{ return $this->nbt; }

	public function missingOrWrongTypeException(string $name, ?Tag $tag) : BlockStateDeserializeException{
		return new BlockStateDeserializeException("Property \"$name\" " . ($tag !== null ? "has unexpected type " . get_class($tag) : "is missing"));
	}

	public function badValueException(string $name, string $stringifiedValue, ?string $reason = null) : BlockStateDeserializeException{
		return new BlockStateDeserializeException(
			"Property \"$name\" has unexpected value \"$stringifiedValue\"" . (
			$reason !== null ? " ($reason)" : ""
		));
	}

	/** @throws BlockStateDeserializeException */
	public function readBool(string $name) : bool{
		$tag = $this->nbt->getTag($name);
		if($tag instanceof ByteTag){
			switch($tag->getValue()){
				case 0: return false;
				case 1: return true;
				default: throw $this->badValueException($name, (string) $tag->getValue());
			}
		}
		throw $this->missingOrWrongTypeException($name, $tag);
	}

	/** @throws BlockStateDeserializeException */
	public function readInt(string $name) : int{
		$tag = $this->nbt->getTag($name);
		if($tag instanceof IntTag){
			return $tag->getValue();
		}
		throw $this->missingOrWrongTypeException($name, $tag);
	}

	/** @throws BlockStateDeserializeException */
	public function readBoundedInt(string $name, int $min, int $max) : int{
		$result = $this->readInt($name);
		if($result < $min || $result > $max){
			throw $this->badValueException($name, (string) $result, "Must be inside the range $min ... $max");
		}
		return $result;
	}

	/** @throws BlockStateDeserializeException */
	public function readString(string $name) : string{
		//TODO: only allow a specific set of values (strings are primarily used for enums)
		$tag = $this->nbt->getTag($name);
		if($tag instanceof StringTag){
			return $tag->getValue();
		}
		throw $this->missingOrWrongTypeException($name, $tag);
	}

	/**
	 * @param int[] $mapping
	 * @phpstan-param array<int, int> $mapping
	 * @phpstan-return int
	 * @throws BlockStateDeserializeException
	 */
	private function parseFacingValue(int $value, array $mapping) : int{
		$result = $mapping[$value] ?? null;
		if($result === null){
			throw new BlockStateDeserializeException("Unmapped facing value " . $value);
		}
		return $result;
	}

	/** @throws BlockStateDeserializeException */
	public function readFacingDirection() : int{
		return $this->parseFacingValue($this->readInt(BlockStateNames::FACING_DIRECTION), [
			0 => Facing::DOWN,
			1 => Facing::UP,
			2 => Facing::NORTH,
			3 => Facing::SOUTH,
			4 => Facing::WEST,
			5 => Facing::EAST
		]);
	}

	/** @throws BlockStateDeserializeException */
	public function readHorizontalFacing() : int{
		return $this->parseFacingValue($this->readInt(BlockStateNames::FACING_DIRECTION), [
			0 => Facing::NORTH, //should be illegal, but 1.13 allows it
			1 => Facing::NORTH, //also should be illegal
			2 => Facing::NORTH,
			3 => Facing::SOUTH,
			4 => Facing::WEST,
			5 => Facing::EAST
		]);
	}

	/** @throws BlockStateDeserializeException */
	public function readWeirdoHorizontalFacing() : int{
		return $this->parseFacingValue($this->readInt(BlockStateNames::WEIRDO_DIRECTION), [
			0 => Facing::EAST,
			1 => Facing::WEST,
			2 => Facing::SOUTH,
			3 => Facing::NORTH
		]);
	}

	/** @throws BlockStateDeserializeException */
	public function readLegacyHorizontalFacing() : int{
		return $this->parseFacingValue($this->readInt(BlockStateNames::DIRECTION), [
			0 => Facing::SOUTH,
			1 => Facing::WEST,
			2 => Facing::NORTH,
			3 => Facing::EAST
		]);
	}

	/** @throws BlockStateDeserializeException */
	public function readColor() : DyeColor{
		//	 * color (StringTag) = black, blue, brown, cyan, gray, green, light_blue, lime, magenta, orange, pink, purple, red, silver, white, yellow
		return match($color = $this->readString(BlockStateNames::COLOR)){
			Values::COLOR_BLACK => DyeColor::BLACK(),
			Values::COLOR_BLUE => DyeColor::BLUE(),
			Values::COLOR_BROWN => DyeColor::BROWN(),
			Values::COLOR_CYAN => DyeColor::CYAN(),
			Values::COLOR_GRAY => DyeColor::GRAY(),
			Values::COLOR_GREEN => DyeColor::GREEN(),
			Values::COLOR_LIGHT_BLUE => DyeColor::LIGHT_BLUE(),
			Values::COLOR_LIME => DyeColor::LIME(),
			Values::COLOR_MAGENTA => DyeColor::MAGENTA(),
			Values::COLOR_ORANGE => DyeColor::ORANGE(),
			Values::COLOR_PINK => DyeColor::PINK(),
			Values::COLOR_PURPLE => DyeColor::PURPLE(),
			Values::COLOR_RED => DyeColor::RED(),
			Values::COLOR_SILVER => DyeColor::LIGHT_GRAY(),
			Values::COLOR_WHITE => DyeColor::WHITE(),
			Values::COLOR_YELLOW => DyeColor::YELLOW(),
			default => throw $this->badValueException(BlockStateNames::COLOR, $color),
		};
	}

	/** @throws BlockStateDeserializeException */
	public function readCoralFacing() : int{
		return $this->parseFacingValue($this->readInt(BlockStateNames::CORAL_DIRECTION), [
			0 => Facing::WEST,
			1 => Facing::EAST,
			2 => Facing::NORTH,
			3 => Facing::SOUTH
		]);
	}

	/** @throws BlockStateDeserializeException */
	public function readFacingWithoutDown() : int{
		$result = $this->readFacingDirection();
		if($result === Facing::DOWN){ //shouldn't be legal, but 1.13 allows it
			$result = Facing::UP;
		}
		return $result;
	}

	public function readFacingWithoutUp() : int{
		$result = $this->readFacingDirection();
		if($result === Facing::UP){
			$result = Facing::DOWN; //shouldn't be legal, but 1.13 allows it
		}
		return $result;
	}

	/**
	 * @phpstan-return Axis::*
	 * @throws BlockStateDeserializeException
	 */
	public function readPillarAxis() : int{
		$rawValue = $this->readString(BlockStateNames::PILLAR_AXIS);
		$value = [
			Values::PILLAR_AXIS_X => Axis::X,
			Values::PILLAR_AXIS_Y => Axis::Y,
			Values::PILLAR_AXIS_Z => Axis::Z
		][$rawValue] ?? null;
		if($value === null){
			throw $this->badValueException(BlockStateNames::PILLAR_AXIS, $rawValue, "Invalid axis value");
		}
		return $value;
	}

	/** @throws BlockStateDeserializeException */
	public function readSlabPosition() : SlabType{
		return $this->readBool(BlockStateNames::TOP_SLOT_BIT) ? SlabType::TOP() : SlabType::BOTTOM();
	}

	/**
	 * @phpstan-return Facing::UP|Facing::NORTH|Facing::SOUTH|Facing::WEST|Facing::EAST
	 * @throws BlockStateDeserializeException
	 */
	public function readTorchFacing() : int{
		return match($rawValue = $this->readString(BlockStateNames::TORCH_FACING_DIRECTION)){
			Values::TORCH_FACING_DIRECTION_EAST => Facing::EAST,
			Values::TORCH_FACING_DIRECTION_NORTH => Facing::NORTH,
			Values::TORCH_FACING_DIRECTION_SOUTH => Facing::SOUTH,
			Values::TORCH_FACING_DIRECTION_TOP => Facing::UP,
			Values::TORCH_FACING_DIRECTION_UNKNOWN => Facing::UP, //should be illegal, but 1.13 allows it
			Values::TORCH_FACING_DIRECTION_WEST => Facing::WEST,
			default => throw $this->badValueException(BlockStateNames::TORCH_FACING_DIRECTION, $rawValue, "Invalid torch facing"),
		};
	}

	/** @throws BlockStateDeserializeException */
	public function readCoralType() : CoralType{
		return match($type = $this->readString(BlockStateNames::CORAL_COLOR)){
			Values::CORAL_COLOR_BLUE => CoralType::TUBE(),
			Values::CORAL_COLOR_PINK => CoralType::BRAIN(),
			Values::CORAL_COLOR_PURPLE => CoralType::BUBBLE(),
			Values::CORAL_COLOR_RED => CoralType::FIRE(),
			Values::CORAL_COLOR_YELLOW => CoralType::HORN(),
			default => throw $this->badValueException(BlockStateNames::CORAL_COLOR, $type),
		};
	}

	/** @throws BlockStateDeserializeException */
	public function readBellAttachmentType() : BellAttachmentType{
		return match($type = $this->readString(BlockStateNames::ATTACHMENT)){
			Values::ATTACHMENT_HANGING => BellAttachmentType::CEILING(),
			Values::ATTACHMENT_STANDING => BellAttachmentType::FLOOR(),
			Values::ATTACHMENT_SIDE => BellAttachmentType::ONE_WALL(),
			Values::ATTACHMENT_MULTIPLE => BellAttachmentType::TWO_WALLS(),
			default => throw $this->badValueException(BlockStateNames::ATTACHMENT, $type),
		};
	}
}