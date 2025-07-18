<?php

namespace App\Traits;

trait AvoidDuplicateConstraintOnSoftDelete
{
    abstract public function getDuplicateAvoidColumns(): array;
    public static function bootAvoidDuplicateConstraintOnSoftDelete(): void
    {
        static::restoring(function ($model): void {
            if ($model->trashed()) {
                foreach ($model->getDuplicateAvoidColumns() as $column) {
                    // handle for Spatie Translatable library
                    if (method_exists($model, 'getTranslatableAttributes')) {
                        $translates = $model->getTranslatableAttributes();
                        if (in_array($column, $translates)) {
                            foreach ($translates as $translate) {
                                if ($translate === $column) {
                                    $values = $model->getTranslations($column);
                                    foreach ($values as $translation => $value) {
                                        $values[$translation] = (explode('--', $value)[1] ?? $value);
                                    }
                                    $model->setTranslations($column, $values);
                                    break;
                                }
                            }
                            continue;
                        }
                    }
                    if ($value = (explode('--', $model->{$column})[1] ?? null)) {
                        $model->{$column} = $value;
                    }
                }
            }
        });
        static::deleted(function ($model): void {
            foreach ($model->getDuplicateAvoidColumns() as $column) {
                // handle for Spatie Translatable library
                if (method_exists($model, 'getTranslatableAttributes')) {
                    $translates = $model->getTranslatableAttributes();
                    if (in_array($column, $translates)) {
                        foreach ($translates as $translate) {
                            if ($translate === $column) {
                                $values = $model->getTranslations($column);
                                foreach ($values as $translation => $value) {
                                    $values[$translation] = time() . '--' . $value;
                                }
                                $model->setTranslations($column, $values);
                                break;
                            }
                        }
                        continue;
                    }
                }
                $model->{$column} = time() . '--' . $model->{$column};
            }
            $model->save();
        });
    }
}
