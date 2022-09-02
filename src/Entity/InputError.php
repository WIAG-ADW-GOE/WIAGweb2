<?php
namespace App\Entity;

class InputError {
    private $section; // 2022-08-30: one of: 'status', 'name', 'person', 'role', 'reference'
    private $msg;

    public function __construct(string $section, $msg = null) {
        $this->section = $section;
        $this->msg = $msg ?? 'Eingabefehler im Abschnitt '.$section.'.';
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
}
