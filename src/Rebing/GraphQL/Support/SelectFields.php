<?php

namespace Rebing\GraphQL\Support;

use Closure;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SelectFields {

    /* @var $args array */
    private static $args = [];
    /* @var $select array */
    private $select = [];
    /* @var $relations array */
    private $relations = [];

    /**
     * @param ResolveInfo $info
     * @param $parentType
     * @param array $args - arguments given with the query
     */
    public function __construct(ResolveInfo $info, $parentType, array $args)
    {
        self::$args = $args;

        $fields = self::getSelectableFieldsAndRelations($info->getFieldSelection(5), $parentType);

        $this->select = $fields[0];
        $this->relations = $fields[1];
    }

    /**
     * Retrieve the fields (top level) and relations that
     * will be selected with the query
     *
     * @return array | Closure - if first recursion, return an array,
     *      where the first key is 'select' array and second is 'with' array.
     *      On other recursions return a closure that will be used in with
     */
    public static function getSelectableFieldsAndRelations(array $requestedFields, $parentType, $topLevel = true)
    {
        $select = [];
        $with = [];

        if(is_a($parentType, ListOfType::class))
        {
            $parentType = $parentType->getWrappedType();
        }
        $primaryKey = app($parentType->config['model'])->getKeyName();

        self::handleFields($requestedFields, $parentType, $select, $with);

        // If a primary key is given, but not in the selects, add it
        if( ! is_null($primaryKey) && ! in_array($primaryKey, $select))
        {
            $select[] = $primaryKey;
        }

        if($topLevel)
        {
            return [$select, $with];
        }
        else
        {
            return function($query) use ($with, $select)
            {
                $query->select($select);
                $query->with($with);
            };
        }
    }

    /**
     * Get the selects and withs from the given fields
     * and recurse if necessary
     */
    protected static function handleFields(array $requestedFields, $parentType, array &$select, array &$with)
    {
        foreach($requestedFields as $key => $field)
        {
            $fieldObject = $parentType->getField($key);

            // First check if the field is even accessible
            $canSelect = self::validateField($fieldObject);
            if($canSelect)
            {
                // With
                if(is_array($field))
                {
                    // Get the next parent type, so that 'with' queries could be made
                    // Both keys for the relation are required (e.g 'id' <-> 'user_id')
                    // Find the foreign key, if it's a 'belongsTo'/'belongsToMany' relation (not a 'hasOne'/'hasMany')
                    $relation = call_user_func([app($parentType->config['model']), $key]);
                    if(is_a($relation, BelongsTo::class) || is_a($relation, BelongsToMany::class))
                    {
                        $foreignKey = $relation->getForeignKey();
                        if( ! in_array($foreignKey, $select))
                        {
                            $select[] = $foreignKey;
                        }
                    }

                    // New parent type, which is the relation
                    $parentType = $parentType->getField($key)->config['type'];

                    $with[$key] = self::getSelectableFieldsAndRelations($field, $parentType, false);
                }
                // Select
                else
                {
                    $select[] = $key;
                }
            }
        }
    }

    /**
     * Check the privacy status, if it's given
     */
    protected static function validateField($fieldObject)
    {
        if(isset($fieldObject->config['privacy']))
        {
            $privacyClass = $fieldObject->config['privacy'];

            // If privacy given as a closure
            if(is_callable($privacyClass))
            {
                return call_user_func($privacyClass, self::$args);
            }

            // If Privacy class given
            return call_user_func([app($privacyClass), 'fire'], self::$args);
        }

        return true;
    }

    public function getSelect()
    {
        return $this->select;
    }

    public function getRelations()
    {
        return $this->relations;
    }

}