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
     * Use a map of old id and new id to change translations base
     */
    public function remap(array $map){
        $mapped = [];
        foreach($map as $old_id => $new_id){
            if (!isset($this->translations[$old_id])) continue;
            $mapped[$old_id] = $this->translations[$old_id];
        }

        foreach($mapped as $old_id => $translation){
            $new_id = $map[$old_id];
            list($context, $original) = explode("\004", $new_id);
            $new_translation = Translation::create($context, $original);
            $this->remove($translation);
            $new_translation = $new_translation->mergeWith($translation);
            $this->add($new_translation);
        }
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