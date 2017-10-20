<?php

namespace Mixmo\Service;

class MixmoService
{
	const CHAR_GANGSTER = '$';
	
    private static $letterList = "AAAAAAAAAABBCCCDDDDEEEEEEEEEEEEEEEEEFFGGGHHHIIIIIIIIIJJKLLLLLLMMMNNNNNNNOOOOOOOPPPQQRRRRRRSSSSSSSTTTTTTTUUUUUUVVVWXYZ**$";

    public static function getRandomLetters()
    {
        $leters1 = str_shuffle(MixmoService::$letterList);
        return str_shuffle($leters1);
    }
}