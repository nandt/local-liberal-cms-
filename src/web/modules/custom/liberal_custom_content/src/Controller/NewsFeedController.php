<?php

namespace Drupal\liberal_custom_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\views\Views;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class NewsFeedController extends ControllerBase {

  public function renderNewsFeed($date = NULL) {

    $today = $this->getTodayDate();
    // if no argument given, then get today's date and resume
    if (!isset($date)) {
      $date = $today;
    }
    else {
      $date = $this->feedInputValidateDate($date);
    }

    // if it was not a valid date stop here and throw 404
    if (!$date) {
      throw new NotFoundHttpException();
    }

    $view_id = 'news_feed_page';
    $view_display_id = 'default';
    $args = [$date];

    $rendered_view = $this->renderView($view_id, $view_display_id, $args);

    /* change the cache to be based on url instead of url.query_tags
     * in specific before sending to render.
     */
    $key = array_search('url.query_args', $rendered_view['#cache']['contexts']);

    // If found, replace it with 'url'.
    if ($key !== FALSE) {
      $rendered_view['#cache']['contexts'][$key] = 'url';
    }

    return $rendered_view;
  }

  public function renderMarketsFeed($date = NULL) {

    $today = $this->getTodayDate();
    // if no argument given, then get today's date and resume
    if (!isset($date)) {
      $date = $today;
    }
    else {
      $date = $this->feedInputValidateDate($date);
    }

    // if it was not a valid date stop here and throw 404
    if (!$date) {
      throw new NotFoundHttpException();
    }

    $view_id = 'markets_feed_page';
    $view_display_id = 'default';
    $args = [$date];

    $rendered_view = $this->renderView($view_id, $view_display_id, $args);

    /* change the cache to be based on url instead of url.query_tags
     * in specific before sending to render.
     */
    $key = array_search('url.query_args', $rendered_view['#cache']['contexts']);

    // If found, replace it with 'url'.
    if ($key !== FALSE) {
      $rendered_view['#cache']['contexts'][$key] = 'url';
    }

    return $rendered_view;
  }

  /**
   * Render a view and get the array
   *
   * @param string $view_id
   * @param string $view_display_id
   * @param array $args
   *
   * @return array
   */
  public function renderView($view_id, $view_display_id, $args) {
    // Load the view and execute it
    $view = Views::getView($view_id);
    $view->setDisplay($view_display_id);
    $view->setArguments($args);
    $view->preExecute();
    $view->execute();

    // Return rendered output of the view
    return $view->buildRenderable($view_display_id);
  }

  /**
   * Function to validate input date for news feed.
   *
   * @param string $check_date
   *   The date string to validate.
   * @param string $format
   *   The expected date format.
   *
   * @return mixed
   * A DateTime object if the date is valid, FALSE otherwise.
   */
  public function feedInputValidateDate($check_date, $format = 'Ymd') {
    $timezone = new \DateTimeZone("Europe/Athens");
    $date = DateTime::createFromFormat($format, $check_date, $timezone);

    return $date && $date->format($format) === $check_date ? $date : FALSE;
  }

  /**
   * Function to validate input date for the news / market feed.
   *
   * @return Datetime
   * A DateTime object.
   */
  public function getTodayDate() {
    $timezone = new \DateTimeZone('Europe/Athens');
    $today = new DateTime('now', $timezone);

    return $today;
  }

}
