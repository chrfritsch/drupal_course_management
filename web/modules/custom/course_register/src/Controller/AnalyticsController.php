<?php

namespace Drupal\course_register\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 *
 */
class AnalyticsController extends ControllerBase {

  /**
   *
   */
  public function dashboard() {
    // Tạo dữ liệu mẫu đơn giản.
    $chart = [
      '#type' => 'chart',
      '#chart_type' => 'line',
      '#title' => 'Test Chart',
      '#xaxis' => [
        'type' => 'category',
        'categories' => ['Jan', 'Feb', 'Mar', 'Apr', 'May'],
      ],
      '#series' => [
        [
          'name' => 'Series 1',
          'data' => [10, 20, 15, 25, 30],
        ],
      ],
    ];

    return [
      '#theme' => 'analytics_dashboard',
      '#content' => [
        '#markup' => '<div class="chart-container">' . render($chart) . '</div>',
      ],
      '#attached' => [
        'library' => ['course_register/analytics_dashboard'],
      ],
    ];
  }

}
