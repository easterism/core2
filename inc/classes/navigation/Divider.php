<?php
namespace Core2\Navigation;


/**
 *
 */
class Divider {

    private $position = '';


    /**
     * @return string
     */
    public function getPosition(): string {

        return $this->position;
    }


    /**
     * @param string $position
     */
    public function setPosition(string $position): void {

        $this->position = $position;
    }


    /**
     * @return array
     */
    public function toArray(): array {

        return [
            'type'     => 'divider',
            'position' => $this->getPosition(),
        ];
    }
}