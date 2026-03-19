<?php
namespace Core2\Navigation;


/**
 *
 */
class Divider {

    private $seq      = 10;
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
     * @return int
     */
    public function getSeq(): int {

        return $this->seq;
    }


    /**
     * @param int $seq
     * @return void
     */
    public function setSeq(int $seq): void {

        $this->seq = $seq;
    }


    /**
     * @return array
     */
    public function toArray(): array {

        return [
            'type'     => 'divider',
            'seq'      => $this->getSeq(),
            'position' => $this->getPosition(),
        ];
    }
}