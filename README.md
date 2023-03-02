**Variation filters**

Модуль drupal, который содержит в себе наработки для переключения вариаций товара через ajax.
В данном слаче товар = node, а вариация = paragraph

1. В файле *variation_filters.module*, в методе *_variation_filters_get_filters* - указываются фильтры. Ключ = машинное имя поля, а значение это заголовок фильтра, который будет отображаться в шаблоне

2. В *\Drupal\variation_filters\Controller\VariationFiltersController* необходимо подредактировать вывод (поля, которые будут изменены, после смены фильтра)

3. В js ajax следует вызывать следующим образом: 

    Drupal.ajax({ "url":"/product_variations/PRODUCT_ID" }).execute();

Например, с формы читаем данные так:

    var varFilter = document.getElementById("variation_filter");
    if (varFilter) {
      let inputs = varFilter.querySelectorAll("input");
      inputs.forEach(el=>{
        el.addEventListener("change",e=>{
          let fd = new FormData(varFilter);
          fd.append("target",el.getAttribute("name"));
          let argsStr = new URLSearchParams(fd).toString();
          let url = varFilter.getAttribute("action")+(argsStr!==""?("?"+argsStr):"");
          console.log(url);
          Drupal.ajax({ "url":url }).execute();
        });
      });
    }

