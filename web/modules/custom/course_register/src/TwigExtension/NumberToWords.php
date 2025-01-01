<?php

namespace Drupal\course_register\TwigExtension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class NumberToWords extends AbstractExtension {

  public function getFilters() {
    return [
      new TwigFilter('number_to_words', [$this, 'numberToWords']),
    ];
  }

  public function getName() {
    return 'number_to_words';
  }

  public function numberToWords($number) {
    $dictionary = array(
      0 => 'không',
      1 => 'một',
      2 => 'hai',
      3 => 'ba',
      4 => 'bốn',
      5 => 'năm',
      6 => 'sáu',
      7 => 'bảy',
      8 => 'tám',
      9 => 'chín',
    );

    if (!is_numeric($number)) {
      return false;
    }

    if ($number == 0) {
      return 'không đồng';
    }

    // Xử lý số âm
    if ($number < 0) {
      return 'âm ' . $this->numberToWords(abs($number));
    }

    $string = '';

    // Xử lý hàng tỷ
    if ($number >= 1000000000) {
      $billions = floor($number / 1000000000);
      $string .= $this->readHundreds($billions) . ' tỷ';
      $number -= $billions * 1000000000;
      if ($number > 0) {
        $string .= ' ';
      }
    }

    // Xử lý hàng triệu
    if ($number >= 1000000) {
      $millions = floor($number / 1000000);
      $string .= $this->readHundreds($millions) . ' triệu';
      $number -= $millions * 1000000;
      if ($number > 0) {
        $string .= ' ';
      }
    }

    // Xử lý hàng nghìn
    if ($number >= 1000) {
      $thousands = floor($number / 1000);
      $string .= $this->readHundreds($thousands) . ' nghìn';
      $number -= $thousands * 1000;
      if ($number > 0) {
        $string .= ' ';
      }
    }

    // Xử lý hàng trăm
    if ($number > 0) {
      $string .= $this->readHundreds($number);
    }

    return $string . ' đồng';
  }

  private function readHundreds($number) {
    $dictionary = array(
      0 => 'không',
      1 => 'một',
      2 => 'hai',
      3 => 'ba',
      4 => 'bốn',
      5 => 'năm',
      6 => 'sáu',
      7 => 'bảy',
      8 => 'tám',
      9 => 'chín',
    );

    $string = '';

    // Xử lý hàng trăm
    $hundreds = floor($number / 100);
    $number -= $hundreds * 100;
    if ($hundreds > 0) {
      $string = $dictionary[$hundreds] . ' trăm';
      if ($number > 0) {
        $string .= ' ';
      }
    }

    // Xử lý hàng chục
    if ($number > 0) {
      $tens = floor($number / 10);
      $ones = $number % 10;

      if ($tens > 1) {
        $string .= $dictionary[$tens] . ' mươi';
        if ($ones > 0) {
          $string .= ' ' . ($ones == 1 ? 'mốt' : ($ones == 5 ? 'lăm' : $dictionary[$ones]));
        }
      }
      elseif ($tens == 1) {
        $string .= 'mười' . ($ones > 0 ? ' ' . ($ones == 5 ? 'lăm' : $dictionary[$ones]) : '');
      }
      elseif ($tens == 0 && $hundreds > 0) {
        $string .= 'lẻ ' . ($ones == 5 ? 'năm' : $dictionary[$ones]);
      }
      else {
        $string .= $dictionary[$ones];
      }
    }

    return $string;
  }
}
