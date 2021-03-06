<?php

namespace Cysha\Casino\Holdem\Game;

use Cysha\Casino\Game\Client;
use Cysha\Casino\Game\Contracts\Dealer as DealerContract;
use Cysha\Casino\Game\Contracts\Player;
use Cysha\Casino\Game\Contracts\Player as PlayerContract;
use Cysha\Casino\Game\PlayerCollection;
use Cysha\Casino\Game\Table as BaseTable;
use Cysha\Casino\Holdem\Exceptions\TableException;
use Ramsey\Uuid\Uuid;

class Table extends BaseTable
{
    /**
     * @var Uuid
     */
    private $id;

    /**
     * @var Dealer
     */
    private $dealer;

    /**
     * @var PlayerCollection
     */
    private $players;

    /**
     * @var PlayerCollection
     */
    private $playersSatOut;

    /**
     * @var int
     */
    private $button = 0;

    /**
     * Table constructor.
     *
     * @param Uuid $id
     * @param DealerContract $dealer
     * @param PlayerCollection $players
     */
    private function __construct(Uuid $id, DealerContract $dealer, PlayerCollection $players)
    {
        $this->id = $id;
        $this->players = $players;
        $this->playersSatOut = PlayerCollection::make();
        $this->dealer = $dealer;
    }

    /**
     * @param Uuid $id
     * @param DealerContract $dealer
     * @param PlayerCollection $players
     *
     * @return Table
     */
    public static function setUp(Uuid $id, DealerContract $dealer, PlayerCollection $players)
    {
        return new self($id, $dealer, $players);
    }

    /**
     * @return Uuid
     */
    public function id(): Uuid
    {
        return $this->id;
    }

    /**
     * @return PlayerCollection
     */
    public function players(): PlayerCollection
    {
        return $this->players;
    }

    /**
     * @return DealerContract
     */
    public function dealer(): DealerContract
    {
        return $this->dealer;
    }

    /**
     * @return int
     */
    public function button(): int
    {
        return $this->button;
    }

    /**
     * @return PlayerContract
     */
    public function locatePlayerWithButton(): PlayerContract
    {
        $buttonLocation = $this->playersSatDown()->get($this->button);
        if ($buttonLocation === null) {
            $this->button = 0;
            $buttonLocation = $this->playersSatDown()->first();
        }

        return $buttonLocation;
    }

    /**
     * @param PlayerContract $player
     */
    public function sitPlayerOut(PlayerContract $player)
    {
        $this->playersSatOut = $this->playersSatOut->push($player);
    }

    /**
     * @return PlayerCollection
     */
    public function playersSatDown(): PlayerCollection
    {
        return $this->players()->diff($this->playersSatOut)->values();
    }

    /**
     * @param PlayerContract $player
     *
     * @throws TableException
     */
    public function giveButtonToPlayer(PlayerContract $player)
    {
        $playerIndex = $this->playersSatDown()
            ->filter
            ->equals($player)
            ->keys()
            ->first();

        if ($playerIndex === null) {
            throw TableException::invalidButtonPosition();
        }

        $this->button = $playerIndex;
    }

    /**
     * Moves the button along the table seats.
     */
    public function moveButton()
    {
        $this->button = $this->button + 1;

        if ($this->button >= $this->playersSatDown()->count()) {
            $this->button = 0;
        }
    }

    /**
     * @param PlayerContract $findPlayer
     *
     * @return int
     */
    public function findSeat(PlayerContract $findPlayer): int
    {
        return $this->players()
            ->filter(function (PlayerContract $player) use ($findPlayer) {
                return $player->equals($findPlayer);
            })
            ->keys()
            ->first();
    }

    /**
     * @param string $playerName
     *
     * @return Player
     */
    public function findPlayerByName($playerName): PlayerContract
    {
        return $this->players()
            ->filter(function (PlayerContract $player) use ($playerName) {
                return $player->name() === $playerName;
            })
            ->first();
    }

    public function removePlayer(Client $client)
    {
        $player = $this->players()
            ->filter(function (Player $player) use ($client) {
                return $player->name() === $client->name();
            })
            ->first()
        ;

        if ($player === null) {
            throw TableException::notRegistered($client, $this);
        }

        $this->players = $this->players()
            ->reject(function (Player $player) use ($client) {
                return $player->name() === $client->name();
            })
            ->values();
    }
}
