<?php


/**
 * Определяет возможность добавления новых пунктов в верхнем меню
 * Interface Navigation
 */
interface Navigation {

    /**
     * Возвращает массив определенного формата описывающий пункты для верхнего меню
     * [
     *   [
     *      "type"  => 'link',
     *      "title" => 'Text',
     *      "icon"  => '<i class="fa fa-star"></i>',
     *      "link"  => 'index.php#module=profile',
     *   ],
     *   [
     *      "type"  => 'list',
     *      "title" => 'Text',
     *      "icon"  => '<i class="fa fa-star"></i>',
     *      "list"  => [
     *          [
     *              "type"  => 'header',
     *              "title" => 'Text',
     *          ],
     *          [
     *              "type"    => "link",
     *              "id"      => "ID Element",
     *              "class"   => "Class Element",
     *              "title"   => "Text",
     *              "link"    => "index.php#module=profile",
     *              "icon"    => '<i class="fa fa-star"></i>',
     *              "onclick" => "alert(123);return false;",
     *          ],
     *          [
     *              "type" => 'divider'
     *          ],
     *          [
     *              "type"     => "file",
     *              "id"       => "ID Element",
     *              "class"    => "Class Element",
     *              "title"    => "Text",
     *              "icon"     => '<i class="fa fa-file"></i>',
     *              "onchange" => "alert('Файл выбран');",
     *          ]
     *       ]
     *    ]
     * ]
     * @return mixed
     */
    public function getNavigationItems();
}