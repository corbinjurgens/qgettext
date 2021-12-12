<?php

namespace Corbinjurgens\QGetText\Concerns;

use Gettext\Translations;
use Gettext\Translation;
class CustomTranslations extends Translations{
    /**
     * Move a built translation instance to this one
     */
	public static function move(Translations $translations){
        $new = new static();
        $new->description = $translations->description;
        $new->translations = $translations->translations;
        $new->headers = $translations->headers;
        $new->flags = $translations->flags;
        return $new;
    }

    /**
     * addOrMerge but allow a custom id, not the one forced from $translation->getId();
     * ie. merge a translations comments etc that has a different text
     */
    public function addOrMergeId(string $id, Translation $translation, int $mergeStrategy = 0){

        if (isset($this->translations[$id])) {
            return $this->translations[$id] = $this->translations[$id]->mergeWith($translation, $mergeStrategy);
        }

        return $this->translations[$id] = $translation;
    }
	
}