<?php

namespace App\ApiResource;

use Symfony\Component\Serializer\Annotation\Groups;

final class SurveyQuestionOutput
{
    #[Groups(['question:read'])]
    public int $id;

    #[Groups(['question:read'])]
    public string $type;

    #[Groups(['question:read'])]
    public string $label;

    #[Groups(['question:read'])]
    public array $options = [];

    public function __construct(int $id, string $type, string $label, array $options = [])
    {
        $this->id = $id;
        $this->type = $type;
        $this->label = $label;
        $this->options = $options;
    }
}
