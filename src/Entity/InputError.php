<?php
namespace App\Entity;

class InputError {
    const ERROR_LEVEL = [
        'info'    => ['info', 'warning', 'error'],
        'warning' => ['warning', 'error'],
        'error'   => ['error'],
    ];

    // values for section
    // 2022-08-30 Person: 'status', 'name', 'person', 'role', 'reference', 'external id'
    private $section;
    private $msg;
    private $level;

    public function __construct(string $section, $msg = null, $level = 'error') {
        $this->section = $section;
        $this->msg = $msg ?? 'Eingabefehler im Abschnitt '.$section.'.';
        $this->level = $level;
    }

    public function __toString(): string {
        return $this->msg;
    }

    public function getSection(): string {
        return $this->section;
    }

    public function getMsg(): string {
        return $this->msg;
    }

    public function getLevel() {
        return $this->level;
    }
}
