<?php

namespace SimulotoBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use SimulotoBundle\Entity\EuromillionsSimulation;
use SimulotoBundle\Util\ISimulotteryController;
use SimulotoBundle\Util\TraitSimulottery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 * Description of EuromillionsController
 *
 * @author Admin
 */
class EuromillionsController extends Controller implements ISimulotteryController
{

    use TraitSimulottery;

    /**
     * Main method of an Euromillions simulation
     *
     * @return JsonResponse for ajax request     *
     */
    public function mainPlayAction(Request $request)
    {

        if ($request->isXmlHttpRequest())
        {
            
            $uNumbers = $request->get('selectedBalls'); //return null
            $uStars = $request->get('selectedStars'); //return null
            $countGames = $request->get('countGames');           
            
            $response = [
                'message' => 'none',
                'uNumbers' => $uNumbers,
                'uStars' => $uStars,
                'countGames' => $countGames,
                'success' => true,
                "status" => 100
            ];

            $validation = $this->isContentValidAction($uNumbers, $uStars, $countGames);

            $isValid = $validation['isValide'];

            $response = [
                'isValid' => $isValid,
                'message' => $validation['message']
            ];

            if ($isValid)
            {
                $eur = new EuromillionsSimulation();

                //good balls for html table "show all draw"
                $arrSimulationDetails = [
                    'draw' => [],
                    'drawStars' => [],
                    'uNumbers' => [],
                    'uStars' => [],
                    'goodBalls' => [],
                    'goodStars' => [],
                    'message' => $validation['message']
                ];

                //hydrate EuromillionsSimulation
                $eur->setUNumbers($uNumbers);
                $eur->setUStars($uStars);
                $eur->setCountGames($countGames);

                /** calcul result of the simulation (controller is using trait methods) * */
                for ($i = 0; $i < $countGames; $i++)
                {

                    $draw = $this->drawBalls($eur->getMinNb(), $eur->getMaxNb(), $eur->getCountDraw());
                    $drawStars = $this->drawBalls($eur->getMinStars(), $eur->getMaxStars(), $eur->getCountDrawStars());

                    $eur->setDraw($draw);
                    $eur->setDrawStars($drawStars);

                    $countUserGrids = count($uNumbers);

                    $goodBalls = $this->goodBalls($uNumbers, $eur->getDraw());
                    $eur->setGoodBalls($goodBalls);

                    $goodStars = $this->goodBalls($uStars, $eur->getDrawStars());
                    $eur->setGoodStars($goodStars);

                    $score = $this->buildScoreAction($eur);

                    $eur->setScore($score);

                    array_push($arrSimulationDetails['draw'], $eur->getDraw());
                    array_push($arrSimulationDetails['drawStars'], $eur->getDrawStars());
                    array_push($arrSimulationDetails['uNumbers'], $eur->getUNumbers());
                    array_push($arrSimulationDetails['uStars'], $eur->getUStars());
                    array_push($arrSimulationDetails['goodBalls'], $goodBalls['goodBallsList']);
                    array_push($arrSimulationDetails['goodStars'], $goodStars['goodBallsList']);
                }
                $response = [
                    "validation" => $validation,
                    "uNumbers" => $uNumbers,
                    "uStars" => $uStars,
                    "countGames" => $countGames,
                    "code" => 100,
                    "success" => true,
                    'score' => $eur->getScore(),
                    'arrSimulationDetails' => $arrSimulationDetails
                ];
            }
        } else if (!$request->isXmlHttpRequest())
        {
            //not an xhmlttprequest
            $response['message'] = 'Demande impossible pour le moment';
            return $this->json($response);
        }
        return $this->json($response);
    }

    /**
     * @param $eur Euromillionssimulation object
     *
     * @return array
     */
    public function buildScoreAction($eur)
    {
        $goodBalls = $eur->getGoodBalls();
        $goodBalls = $goodBalls['countGoodBalls'];

        $goodStars = $eur->getGoodStars();
        $goodStars = $goodStars['countGoodBalls'];

        $score = $eur->getScore();

        if ($goodStars == 0)
        {
            switch ($goodBalls)
            {
                case 5: $score['3'] ++;
                    break;
                case 4: $score['6'] ++;
                    break;
                case 3: $score['10'] ++;
                    break;
                case 2: $score['13'] ++;
                    break;
            }
            return $score;
        } elseif ($goodStars == 1)
        {
            switch ($goodBalls)
            {
                case 5: $score['2'] ++;
                    break;
                case 4: $score['5'] ++;
                    break;
                case 3: $score['9'] ++;
                    break;
                case 2: $score['12'] ++;
                    break;
            }
            return $score;
        } elseif ($goodStars == 2)
        {
            switch ($goodBalls)
            {
                case 5: $score['1'] ++;
                    break;
                case 4: $score['4'] ++;
                    break;
                case 3: $score['7'] ++;
                    break;
                case 2: $score['8'] ++;
                    break;
                case 1: $score['11'] ++;
                    break;
            }
            return $score;
        }

        return false;
    }

    /**
     * check if ajax data format is correct: $arrUNumbers must be an array into an array containing digits
     * and each array must have a size between 6 and 10 [ [3, 15, 23, 27, 34, 45], [9, 14, 44, 23, 11, 30], ... ]
     * and $countGames must be a digit
     *
     * @return array
     * * */
    public function isContentValidAction(array $arrUNumbers, array $arrUStars, $countGames)
    {
        /**
         * 1. Testing countGames 
         * 2. Testing $arrNumbers
         * 3. Testing $arrUStars
         * 4. Testing pattern grid multiple (is this simulation a legal multiple pattern ?)
         */
        $validation = [
            'isValide' => false,
            'message' => 'none'
        ];


        if (is_numeric($countGames) && $countGames > 0)
        {
            if ($countGames !== "1" && $countGames !== "10" && $countGames !== "100" && $countGames !== "1000")
            {
                $validation['message'] = 'Erreur veuillez recommencer Error 1';
                $validation['countGame'] = $countGames;
                return $validation;
            }
        } else
        {
            $validation['message'] = 'Erreur veuillez recommencer. Error 2';
            return $validation;
        }

        /**
         * exemple of $arrUNumbers
         *
         * [ [11, 33, 12, 31, 29, 44], [3, 19, 45, 38, 27, 10], etc . . . ]
         */
        if (is_array($arrUNumbers) && count($arrUNumbers) > 4 && count($arrUNumbers) <= 10)
        {
            foreach ($arrUNumbers as $number)
            {
                if (is_numeric($number))
                {


                    if ($number >= 1 && $number <= 50)
                    {
                        $size = count($number);


                        if (!is_numeric($number))
                        {
                            $validation['message'] = 'Erreur veuillez recommencer. Error 3';
                            $validation['debug'] = $number;
                            return $validation;
                        }
                    } else
                    {
                        $validation['message'] = 'Erreur veuillez recommencer. Error 4';
                        return $validation;
                    }
                } else
                {
                    $validation['message'] = 'Error 5';
                    return $validation;
                }
            }
        } else
        {
            $validation['message'] = 'Erreur: Vous devez selectionner minimum 5 chiffres et maximum 10 chiffres. Error 6';
            return $validation;
        }
        /**
         * exemple of $arrUStars
         *
         * [1, 2, 3, 4]
         */
        $sizeArrStars = count($arrUStars);

        if ($sizeArrStars > 1 && $sizeArrStars <= 11)
        {

            for ($i = 0; $i < $sizeArrStars; $i++)
            {
                if (!is_numeric($arrUStars[$i]))
                {
                    $validation['message'] = 'Error 7';
                    return $validation;
                }
            }
        } else
        {
            $validation['message'] = 'Vous devez selectionner minimum 1 &eacute;toile et maximum 11 &eacute;toiles. Error 8';
            return $validation;
        }

        /** at this point datas are valids * */
        /** test if uBalls and uStars respect grid "multiple" patterns * */
        $ballsLength = count($arrUNumbers);
        $starsLength = count($arrUStars);

        if ($ballsLength >= 5 && $ballsLength <= 7)
        {
            if ($starsLength > 1)
            {
                $validation['patternMultiple'] = true;
            }
        }
        if ($ballsLength == 8)
        {
            if ($starsLength > 1 && $starsLength <= 7)
            {
                $validation['patternMultiple'] = true;
            }
        }
        if ($ballsLength == 9)
        {
            if ($starsLength > 1 && $starsLength <= 5)
            {
                $validation['patternMultiple'] = true;
            }
        }
        if ($ballsLength == 10)
        {
            if ($starsLength == 2 || $starsLength == 3)
            {
                $validation['patternMultiple'] = true;
            }
        }
        if ($validation['patternMultiple'] !== true)
        {
            $validation['message'] = 'Vous devez entrer une grille multiple avec un nombre de numéros et d\'étoiles valides';
            $validation['patternMultiple'] = false;
            $validation['isValide'] = false;
        } else
        {
            $validation['isValide'] = true;
        }

        return $validation;
    }

}
