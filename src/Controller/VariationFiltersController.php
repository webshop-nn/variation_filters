<?php

namespace Drupal\variation_filters\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;

/**
 * Returns responses for Фильтры вариаций routes.
 */
class VariationFiltersController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build(?int $nid) {
    $response = new AjaxResponse();

    // Варианты фильтров
    $filters = array_keys(_variation_filters_get_filters());

    // Элемент, на который мы кликнули должен быть на первом месте
    if (isset($_GET['target']) && in_array($_GET['target'], $filters)) {
      unset($filters[$_GET['target']]);
      array_unshift($filters,$_GET['target']);
    }

    $database = \Drupal::database();

    // Ищем фильтры, которые выдают пустоту. Если у фильтра не существует значений, то выгоняем его из фильтров
    $query = $database->select('node__field_variations', 'vr');
    $query->condition("vr.bundle","product");
    $query->condition("vr.entity_id",$nid);
    $query->addExpression("COUNT(field_variations_target_id)","C");
    $query->groupby("field_variations_target_id");
    foreach ($filters as $key => $filter) {
      if (isset($_GET[$filter])) {

        // Создадим грушу для битья, которую можно бить, не боясь последствий. В случае, если результат запроса будет неудачный - мы откатимся к $query
        $queryTemp = clone $query;

        // Добавим поле из текущего цикла
        $table = "A".$key;
        $queryTemp->join("paragraph__".$filter,$table,"vr.field_variations_target_id = ".$table.".entity_id");
        $queryTemp->condition($table.".".$filter."_target_id",$_GET[$filter]);

        // Проверяем запрос на наличие пустоты
        $result = $queryTemp->execute()->fetch();
        if ($result) {
          $query = $queryTemp;
        } else {
          unset($filters[$key]);
        }

      }
    }

    // Грузим нужную вариацию
    $query = $database->select('node__field_variations', 'vr'); // product_variation
    $query->condition("vr.bundle","product");
    $query->condition("vr.entity_id",$nid);
    $query->addField("vr","field_variations_target_id","id");
    $query->range(0,1);

    // Наполняем запрос фильтрами
    foreach ($filters as $key => $filter) {
      if (isset($_GET[$filter])) {
        $table = "A".$key;
        $query->join("paragraph__".$filter,$table,"vr.field_variations_target_id = ".$table.".entity_id");
        $query->condition($table.".".$filter."_target_id",$_GET[$filter]);
      }
    }

    // Получаем результат
    $result = $query->execute()->fetch();

    // Проверяем результат на пустоту
    if (!is_object($result) || !isset($result->id)) {
      $response->addCommand(new AlertCommand("Я ниче не нашел :("));
      return $response;
    }

    // Пытаемся загрузить объект
    $paragraph = \Drupal::entityTypeManager()->getStorage("paragraph")->load($result->id);

    // На всякий случай и его проверим
    if (!is_object($paragraph) || $paragraph->getType() != "product_variation") {
      $response->addCommand(new AlertCommand("Я ниче не нашел :("));
      return $response;
    }

    // Полная замена контента
    $fields = [
      "field_images_small" => $paragraph->field_images->view("slider_small"),
      "field_images"       => $paragraph->field_images->view("default")
    ];
    foreach ($fields as $field => $content) {
      $response->addCommand(new ReplaceCommand(".event__replace__".$field,$content));
    }

    // Замена контента, без обертки
    $fields = [
      // "field_quantity" => $paragraph->field_quantity->view("default"),
      // "field_price"    => $paragraph->field_price->view("default"),
      // "field_lot_min"  => $paragraph->field_lot_min->view("default"),
      "field_chars"    => $paragraph->field_chars->view("default"),
    ];
    foreach ($fields as $field => $content) {
      $response->addCommand(new HtmlCommand(".event__replace__".$field,$content));
    }

    // Скрываем или показываем вкладку характеристик
    if ($paragraph->field_chars->count() > 0) {
      $response->addCommand(new InvokeCommand(".event__tabs__chars","removeClass",["hidden"]));
    } else {
      $response->addCommand(new InvokeCommand(".event__tabs__chars","addClass",   ["hidden"]));
    }

    // Заменяем сам фильтр
    $response->addCommand(new ReplaceCommand("#variation_filter",[
      "#theme"        => "variation_filters",
      "#product"      => $nid,
      "#variation_id" => $paragraph->id()
    ]));

    return $response;
  }

}
