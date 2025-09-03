<?php

namespace App\ApiResource;

use Symfony\Component\Serializer\Annotation\Groups;

final class SurveyOptionOutput
{
    #[Groups(['questionnaire:read'])]
    public int $id;

    #[Groups(['questionnaire:read'])]
    public string $label;

    public function __construct(int $id, string $label)
    {
        $this->id = $id;
        $this->label = $label;
    }
}
