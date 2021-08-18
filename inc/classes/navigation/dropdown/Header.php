<?php
namespace Core2\Navigation\Dropdown;


/**
 *
 */
class Header {

    private $title = '';


    /**
     * @return string
     */
    public function getTitle(): string {

        return $this->title;
    }


    /**
     * @param string $title
     */
    public function setTitle(string $title): void {

        $this->title = $title;
    }


    /**
     * @return array
     */
    public function toArray(): array {

        return [
            'type'  => 'header',
            'title' => $this->getTitle(),
        ];
    }
}