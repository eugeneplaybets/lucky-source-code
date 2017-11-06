<?php

class GameService implements GameContract
{
    private $betGrow = [
        /*bids*/
    ];

    /**
     * @param int $userId
     * @param float $rate
     *
     * @return array
     *
     * @since version
     */
    public function start(int $userId, float $rate): array
    {
        $this->checkActiveGame($userId);
        $game = $this->createGame($userId, $rate);
        $this->generateGameTable($game->id);
        
        return ['success' => $game->id];
    }

    /**
     * @param int $userId
     */
    public function createTotalGameUser(int $userId)
    {
        $user = new LuckyTotalGame();
        $user->id = $userId;
        $user->save();
    }

    /**
     * @param int $gameId
     * @param int $index
     * @param float $money
     * @param string $tableData
     */
    public function newStep(int $gameId, int $index, float $money, string $tableData)
    {
        $game = new LuckyGameStep();
        $game->id = $gameId;
        $game->index = $index;
        $game->money = $money;
        $game->result = json_decode($tableData)->{$index};
        $game->save();
    }

    /**
     * @param int $userId
     * @param float $amount
     */
    public function createTotalSupplyUser(int $userId, float $amount)
    {
        $user = new LuckyTotalSupply();
        $user->id = $userId;
        $user->amount = $amount;
    }

    /**
     * @param int $gameId
     */
    public function endGame(int $gameId)
    {
        $game = LuckyGame::findOrFail($gameId);
        $game->end_date = now();
        $game->result = 0;
        $game->update();

        $userService = app()->make(UserContract::class);
        $userService->addBalance(UserService::SERVICE_ID, $game->bid);

        $this->addToTotalGames($game->id);
    }

    /**
     * @param int $gameId
     * @return float
     */
    public function winGame(int $gameId)
    {
        $game = LuckyGame::findOrFail($gameId);
        $game->end_date = now();
        $game->result = 1;
        $game->save();

        $this->addToTotalGames(session()->get('id'));
        $steps = LuckyGameStep::where('id', $gameId)->orderBy('created_at', 'DESC')->first();

        $userService = app()->make(UserContract::class);
        $userService->addBalance(session()->get('id'), $steps->money);

        $supply = new LuckyTotalSupply();
        $supply->id = session()->get('id');
        $supply->amount = $steps->money;
        $supply->save();

        return $steps->money;
    }

    /**
     * @param int $gameId
     * @return float
     */
    public function getReward(int $gameId)
    {
        $game = LuckyGame::findOrFail($gameId);
        $game->end_date = now();

        // net, ne trogal
        $game->result = 1;
        $game->save();

        $this->addToTotalGames(session()->get('id'));
   
        $tokensForUser = LuckyGameStep::where('id', $gameId)->orderBy('created_at', 'desc')->first()->money;
        
        $userService = app()->make(UserContract::class);
        $userService->addBalance(session()->get('id'), $tokensForUser);

        $supply = new LuckyTotalSupply();
        $supply->id = session()->get('id');
        $supply->amount = $tokensForUser;
        $supply->save();

        return $tokensForUser;
    }

    /**
     * @param int $id
     *
     * @return mixed
     *
     * @since version
     */
    public function tokensForStep(int $id)
    {
        $steps = LuckyGameStep::select('id')->where('id', $id)->count(); 
        $rate = LuckyGame::findOrFail($id)->bid;

        if (10 === $steps) {
            $gameId = LuckyGame::where('id', session()->get('id'))->whereNull('result')->first()->id;

            $game = new LuckyGameStep();
            $game->id = $gameId;
            $game->index = rand(1, 2);
            $game->money = $this->betGrow[$steps];
            $game->result = 1;
            $game->save();
        }


        return $rate * $this->betGrow[0 === $steps ? 0 : --$steps]; 
    }

    public function tokensForStep2(int $id)
    {
        $steps = LuckyGameStep::select('id')->where('id', $id)->count(); 
        $rate = LuckyGame::findOrFail($id)->bid; 

        return $rate * $this->betGrow[$steps]; 
    }

    /**
     * @param int $gameId
     */
    private function generateGameTable(int $gameId)
    {
        $data = [];

        for ($i = 1; $i < 21; $i++) {

            $data[$i] = rand(0, 1);

            if (1 < $i && ($i % 2) === 0) {
                if ($data[$i - 1] === $data[$i]) {
                    $data[$i - 1] = 0 === $data[$i] ? 1 : 0;
                }
            }

            if (3 <= $i) {
                if ($data[$i] === $data[$i - 1] && $data[$i] === $data[$i - 2] && $data[$i] === $data[$i - 3]) {
                    $data[$i] = 0 === $data[$i] ? 1 : 0;
                }
            }
        }

        $table = new LuckyGameTable();
        $table->id = $gameId;
        $table->data = json_encode($data);
        $table->save();
    }

    /**
     * @param int $userId
     * @param float $rate
     *
     * @return LuckyGame
     *
     * @since version
     */
    private function createGame(int $userId, float $rate): LuckyGame
    {
        $game = new LuckyGame();
        $game->id = $userId;
        $game->start_date = now();
        $game->bid = $rate;
        $game->save();

        return $game;
    }

    /**
     * @param int $userId
     */
    private function checkActiveGame(int $userId)
    {
        if ($game = LuckyGame::where('id', $userId)->whereNull('end_date')->first()) {
            $game->end_date = now();
            $game->result = 0;
            $game->update();
            $this->addToTotalGames($userId);
        }
    }

    /**
     * @param int $userId
     */
    private function addToTotalGames(int $userId)
    {
        $game = LuckyTotalGame::where('id', $userId)->first();
        $game->amount += 1;
        $game->update();
    }
}
