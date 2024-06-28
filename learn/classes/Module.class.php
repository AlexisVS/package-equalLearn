<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace learn;

use equal\orm\Collection;
use equal\orm\Model;
use equal\orm\ObjectManager;
use Exception;

class Module extends Model
{

	public static function getColumns(): array
	{
		return [
			'identifier' => [
				'type'        => 'integer',
				'description' => 'Unique identifier the module within the course.',
				'default'     => 1
			],

			'order' => [
				'type'        => 'integer',
				'description' => 'Position of the module in the course.'
			],

			'name' => [
				'type'  => 'alias',
				'alias' => 'title'
			],

			'title' => [
				'type'        => 'string',
				'required'    => true,
				'description' => "Description of the module as presented to user.",
				'multilang'   => true
			],

			'link' => [
				'type'        => 'computed',
				'description' => "URL to visual editor of the module.",
				'function'    => 'calcLink',
				'result_type' => 'string',
				'usage'       => 'uri/url',
				'multilang'   => true
			],

			'page_count' => [
				'type'        => 'computed',
				'description' => "Total amount of pages in the module.",
				'function'    => 'calcPageCount',
				'result_type' => 'integer',
				'store'       => true
			],

			'chapter_count' => [
				'type'        => 'computed',
				'description' => "Total amount of chapters in the module.",
				'function'    => 'calcChapterCount',
				'result_type' => 'integer',
				'store'       => true
			],

			'description' => [
				'type'      => 'string',
				'usage'     => 'text/plain',
				'multilang' => true
			],

			'duration' => [
				'type'        => 'computed',
				'description' => "Total duration of chapters in the module.",
				'function'    => 'calcChaptersDuration',
				'result_type' => 'integer'
			],

			'chapters' => [
				'type'  => 'alias',
				'alias' => 'chapters_ids'
			],

			'chapters_ids' => [
				'type'           => 'one2many',
				'foreign_object' => 'learn\Chapter',
				'foreign_field'  => 'module_id',
				'order'          => 'order',
				'sort'           => 'asc',
				'ondetach'       => 'delete',
				'onupdate'       => 'onupdateChaptersIds'
			],

			'course_id' => [
				'type'           => 'many2one',
				'foreign_object' => 'learn\Course',
				'description'    => 'Course the module relates to.',
				'ondelete'       => 'cascade'
				// delete module when parent course is deleted
			]
		];
	}

	/**
	 * @throws Exception
	 */
	public static function calcLink(Collection $self, ObjectManager $om, $lang): array
	{
		$result = [];

		$self->read(['course_id']);

		foreach ($self as $id => $module) {
			$course_id = $module['course_id'];
			$course_title = $om->read('learn\Course', $course_id, ['title'], $lang)[$course_id]['title'];
			$result[$id] = '/learning/#/course/' . Course::formatLinkIdNumber($course_id) . '/' . Course::createSlug($course_title) . '?mode=edit' . '&module=' . $id . '&lang=' . $lang;
		}

		return $result;
	}


	public static function calcPageCount($om, $oids, $lang): array
	{
		$result = [];

		$modules = $om->read(__CLASS__, $oids, ['chapters_ids'], $lang);

		foreach ($modules as $oid => $module) {
			$chapters = $om->read('learn\Chapter', $module['chapters_ids'], ['page_count'], $lang);
			$result[$oid] = 0;
			foreach ($chapters as $chapter) {
				$result[$oid] += $chapter['page_count'];
			}
		}

		return $result;
	}

	public static function calcChapterCount(Collection $self)
	{
		$result = [];

		$self->read(['chapters_ids']);

		foreach ($self as $id => $module) {
			$result[$id] = count($module['chapters_ids']);
		}

		return $result;
	}

	public static function onupdateChaptersIds($orm, $oids, $values, $lang)
	{
		// force immediate refresh chapter_count
		$orm->write(__CLASS__, $oids, ['chapter_count' => null], $lang);
		$orm->read(__CLASS__, $oids, ['chapter_count'], $lang);
	}

	public static function calcChaptersDuration($self): array
	{
		$result = [];
		/** @var $self Collection */
		$self->read(['chapters_ids' => ['duration']]);

		foreach ($self as $id => $module) {
			$moduleDurationCount = 0;

			foreach ($module['chapters_ids'] as $chapter) {
				$moduleDurationCount += $chapter['duration'];
			}

			$result[$id] = $moduleDurationCount;
		}

		return $result;
	}


}