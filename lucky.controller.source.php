<?php

class GameController extends Controller
{
    /**
     * @var GameContract
     */
    private $service;

    /**
     * GameController constructor.
     * @param GameContract $service
     */
    public function __construct(GameContract $service)
    {
        $this->service = $service;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function start(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rate' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['response' => $validator->messages()], 406);
        }

        $userId = session()->get('user_id');

        if (!LuckyUser::findOrFail($userId)) {
            return response()->json(['error' => 'no database id'], 406);
        }

        $rate = $request->rate;
        $userService = app()->make(UserContract::class);

        if ($rate > $userService->getBalance($userId)) {
            return response()->json(['response' => 'no money'], 406);
        }

        $userService->subBalance($userId, $rate);

        $result = $this->service->start($userId, $rate);

        if (array_key_exists('success', $result)) {

            $rateData = $userService->convertCurrencyName($this->convertF($userService->getCurrency($userId)));

            return response()->json(
                [
                    'response' => [
                        'game_id' => $result['success'],
                        'rate' => $rateData['amount'],
                        'prize' => $userService->getPrize($userId),
                        'currency' => $rateData['currency']
                    ]
                ], 200);
        }

        return response()->json(['error' => $result['error']],406);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function stepInGame(Request $request): JsonResponse
    {
        $result = LuckyGameTable::where('game_id', $request->game)->first()->data;

        if (!$result) {
            return response()->json(['response' => 'Incorrect id of game'], 406);
        }

        $this->service->newStep($request->game, $request->index, $this->service->tokensForStep2($request->game), $result);
        $winOrNope = json_decode($result)->{$request->index};
        $amount = $this->service->tokensForStep($request->game);

        if (0 === $winOrNope) {
            $this->service->endGame($request->game);
            $amount = (float) 0;
        }

        return response()->json([
            'response' => [
                'state' => $winOrNope,
                'amount' => $amount
            ]
        ], 200);
    }

    /**
     * @param int $id
     * @return JsonResponse
     */
    public function resultOfGame(int $id): JsonResponse
    {
        return response()->json([
            'response' => json_decode(LuckyGameTable::where('game_id', $id)->first()->data)
        ], 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function winedGame(Request $request): JsonResponse
    {
        return response()->json(['response' => $this->service->winGame($request->game)], 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getReward(Request $request): JsonResponse
    {
        return response()->json(['response' => $this->service->getReward($request->game)], 200);
    }

    /**
     * @param Request $request
     */
    public function finishGame(Request $request)
    {
        $this->service->endGame($request->game);
    }


    /**
     * @param string $name
     *
     * @return string
     *
     * @since version
     */
    private function convertF(string $name): string
    {
        if ("PLT" === $name) {
            return 'playbets';
        } elseif ("BTC" === $name) {
            return 'bitcoin';
        } elseif ("ETH" === $name) {
            return 'ethereum';
        }
    }
}