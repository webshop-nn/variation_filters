<?php

namespace Drupal\variation_filters\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Defines 'vf_random_stock' queue worker.
 *
 * @QueueWorker(
 *   id = "vf_random_stock",
 *   title = @Translation("Random stok in the products"),
 *   cron = {"time" = 60}
 * )
 */
class VfRandomStock extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $itype = isset($data["itype"])?$data["itype"]:"queue";
    switch ($itype) {

    /**
     * Не спеша добавляем все вариации товаров в работу
     */
    case "queue":
      $query = \Drupal::entityQuery("paragraph");
      $query->condition("type","product_variation");
      $query->exists("field_reminder_max");
      $query->exists("field_reminder_min");
      $offset = isset($data["offset"])?$data["offset"]:0;
      $query->range($offset,1000);
      $entitys = $query->execute();
      
      $queue = \Drupal::queue('vf_random_stock');
      $count = count($entitys);
      if ($count >= 500) {
        \Drupal::logger('variation_filters')->info("Добавлена очередь на обнаружение вариаций с offset = ".($offset+1000));
        $queue->createItem([ "offset" => $offset+1000 ]);
      }

      foreach ($entitys as $id) {
        $queue->createItem([
          "itype" => "work",
          "id"    => $id
        ]);
      }

      \Drupal::logger('variation_filters')->info("Добавлено ".$count." вариаций товаров на обновление.");

      break;

    /**
     * Для вариации товара надо установить случайный остаток товаров
     */
    case "work":

      if (!isset($data["id"])) {
        \Drupal::logger('variation_filters')->warning("неконнектный запуск work. id не найден.");
        return;
      }

      $paragraph = \Drupal::entityTypeManager()->getStorage("paragraph")->load($data["id"]);

      if (is_object($paragraph) && $paragraph->getType() === "product_variation") {

        if ($paragraph->field_reminder_min->count() > 0 && $paragraph->field_reminder_max->count() > 0) {

          $min = (int)$paragraph->field_reminder_min->value;
          $max = (int)$paragraph->field_reminder_max->value;

          if ($min > $max) {
            \Drupal::logger('variation_filters')->info("У вариации товара с id '".$data["id"]."' min больше чем max.");
            return;
          } elseif ($min === $max) {
            $paragraph->set("field_quantity",$min);
          } else
            $paragraph->set("field_quantity",random_int((int)$paragraph->field_reminder_min->value,(int)$paragraph->field_reminder_max->value));
          $paragraph->save();
          // \Drupal::logger('variation_filters')->info("Вариация товара с id '".$data["id"]."' обновлена.");

        }

      } else {
        \Drupal::logger('variation_filters')->warning("Вариация товара с id '".$data["id"]."' не обнаружена.");
      }

    }
  }

}
