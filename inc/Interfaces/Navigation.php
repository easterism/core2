<?php


/**
 * Определяет возможность добавления новых пунктов в верхнем меню
 * Interface Navigation
 */
interface Navigation {

    /**
     * Установка пунктов верхнего меню
     * @param \Core2\Navigation $nav
     * @return void
     */
    public function navigationItems(\Core2\Navigation $nav);
}