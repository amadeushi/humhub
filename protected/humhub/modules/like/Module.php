<?php

namespace humhub\modules\like;

use humhub\modules\like\models\Like;

/**
 * This module provides like support for Content and Content Addons
 * Each wall entry will get a Like Button and a overview of likes.
 *
 * @package humhub.modules_core.like
 * @since 0.5
 */
class Module extends \yii\base\Module
{

    public $isCoreModule = true;

    /**
     * On User delete, also delete all comments
     *
     * @param type $event
     */
    public static function onUserDelete($event)
    {
        foreach (Like::findAll(array('created_by' => $event->sender->id)) as $like) {
            $like->delete();
        }

        return true;
    }

    public static function onActiveRecordDelete($event)
    {
        $record = $event->sender;
        if ($record->hasAttribute('id')) {
            foreach (Like::findAll(array('object_id' => $record->id, 'object_model' => $record->className())) as $like) {
                $like->delete();
            }
        }
    }

    /**
     * On run of integrity check command, validate all module data
     *
     * @param type $event
     */
    public static function onIntegrityCheck($event)
    {
        $controller = $event->sender;
        $controller->showTestHeadline("Like (" . Like::find()->count() . " entries)");

        $i = 0;
        /*
          foreach (Like::model()->findAll() as $l) {
          if ($l->source === null) {
          $integrityChecker->showFix("Deleting like id " . $l->id . " without existing target!");
          if (!$integrityChecker->simulate)
          $l->delete();
          }
          $i++;
          }
         */

        /**
         * Looking up "NewLike" activities which are not linked against a Like record 
         * This has changed in 0.20 - before it was linked against a Content/ContentAddon
         */
        $likes = (new \yii\db\Query())->select("activity.*, like.id as likeid")->from('activity')
                        ->leftJoin('like', 'like.object_model=activity.object_model AND like.object_id=activity.object_id')
                        ->where(['class' => 'humhub\modules\like\activities\Liked'])->andWhere('like.id IS NOT NULL')->andWhere('activity.object_model != :likeClass', [':likeClass' => models\Like::className()])->all();
        foreach ($likes as $like) {
            Yii::$app->db->createCommand()->update('activity', ['object_model' => Like::className(), 'object_id' => $like['likeid']], ['id' => $like['id']])->execute();
        }
    }

    /**
     * On initalizing the wall entry controls also add the like link widget
     *
     * @param type $event
     */
    public static function onWallEntryLinksInit($event)
    {
        $event->sender->addWidget(widgets\LikeLink::className(), array('object' => $event->sender->object), array('sortOrder' => 10));
    }

}
