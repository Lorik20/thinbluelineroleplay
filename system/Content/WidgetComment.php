<?php
/**
 * @brief		Content Item Feed Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	forums
 * @since		16 Oct 2014
 */

namespace IPS\Content;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Content Comment Feed Widget
 */
abstract class _WidgetComment extends \IPS\Widget\PermissionCache
{
	/**
	 * Class
	 */
	protected static $class;

	/**
	 * @brief	Moderator permission to generate caches on [optional]
	 */
	protected $moderatorPermissions	= array();

	/**
	 * Constructor
	 *
	 * @param	String				$uniqueKey				Unique key for this specific instance
	 * @param	array				$configuration			Widget custom configuration
	 * @param	null|string|array	$access					Array/JSON string of executable apps (core=sidebar only, content=IP.Content only, etc)
	 * @param	null|string			$orientation			Orientation (top, bottom, right, left)
	 * @param	boolean				$allowReuse				If true, when the block is used, it will remain in the sidebar so it can be used again.
	 * @param	string				$menuStyle				Menu is a drop down menu, modal is a bigger modal panel.
	 * @return	void
	 */
	public function __construct( $uniqueKey, array $configuration, $access=null, $orientation=null )
	{
		parent::__construct( $uniqueKey, $configuration, $access, $orientation );

		if( count( $this->moderatorPermissions ) )
		{
			$cacheKeyChecks	= array();

			foreach( $this->moderatorPermissions as $permission )
			{
				$cacheKeyChecks[]	= \IPS\Member::loggedIn()->modPermission( $permission );
			}

			$this->cacheKey .= '_' . implode( '_', $cacheKeyChecks );
		}
	}

	/**
	 * Specify widget configuration
	 *
	 * @param	null|\IPS\Helpers\Form	$form	Form object
	 * @return	\IPS\Helpers\Form
	 */
	public function configuration( &$form=null )
	{
		/* Init */
		$class     = static::$class;
		$itemClass = $class::$itemClass;
		
 		if ( $form === null )
		{
	 		$form = new \IPS\Helpers\Form;
 		}
 		
 		/* Block title */ 		
		$form->add( new \IPS\Helpers\Form\Text( 'widget_feed_title', isset( $this->configuration['widget_feed_title'] ) ? $this->configuration['widget_feed_title'] : \IPS\Member::loggedIn()->language()->addToStack( $class::$title . '_pl' ) ) );
		
		/* Container */
		if ( isset( $itemClass::$containerNodeClass ) )
		{
			$form->add( new \IPS\Helpers\Form\Node( 'widget_feed_container_' . $itemClass::$title, isset( $this->configuration['widget_feed_container'] ) ? $this->configuration['widget_feed_container'] : 0, FALSE, array(
				'class'           => $itemClass::$containerNodeClass,
				'zeroVal'         => 'all',
				'permissionCheck' => 'view',
				'multiple'        => true
			) ) );
		}
		
		/* Use permissions? */
		if ( in_array( 'IPS\Content\Permissions', class_implements( $itemClass ) ) )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'widget_feed_use_perms', isset( $this->configuration['widget_feed_use_perms'] ) ? $this->configuration['widget_feed_use_perms'] : TRUE, FALSE ) );
		}
		
		/* Types */
		if ( in_array( 'IPS\Content\Lockable', class_implements( $itemClass ) ) )
		{
			$types = array(
				'any'    => 'mod_confirm_either',
				'open'   => 'mod_confirm_unlock',
				'closed' => 'mod_confirm_lock'
			);

			$form->add( new \IPS\Helpers\Form\Radio( 'widget_feed_item_status_locked', isset( $this->configuration['widget_feed_item_status_locked'] ) ? $this->configuration['widget_feed_item_status_locked'] : 'any', FALSE, array( 'options' => $types ) ) );
		}
		if ( in_array( 'IPS\Content\Pinnable', class_implements( $itemClass ) ) )
		{
			$types = array(
				'any'       => 'mod_confirm_either',
				'pinned'    => 'mod_confirm_pin',
				'notpinned' => 'mod_confirm_unpin'
			);

			$form->add( new \IPS\Helpers\Form\Radio( 'widget_feed_item_status_pinned', isset( $this->configuration['widget_feed_item_status_pinned'] ) ? $this->configuration['widget_feed_item_status_pinned'] : 'any', FALSE, array( 'options' => $types ) ) );
		}
		if ( in_array( 'IPS\Content\Featurable', class_implements( $itemClass ) ) )
		{
			$types = array(
				'any'         => 'mod_confirm_either',
				'featured'    => 'mod_confirm_feature',
				'notfeatured' => 'mod_confirm_unfeature'
			);

			$form->add( new \IPS\Helpers\Form\Radio( 'widget_feed_item_status_featured', isset( $this->configuration['widget_feed_item_status_featured'] ) ? $this->configuration['widget_feed_item_status_featured'] : 'any', FALSE, array( 'options' => $types ) ) );
		}
		if ( in_array( 'IPS\Content\Hideable', class_implements( $class ) ) )
		{
			$types = array(
				'any'         => 'mod_confirm_either',
				'visible'     => 'mod_confirm_visible',
				'hidden'      => 'mod_confirm_hidden'
			);
	
			$form->add( new \IPS\Helpers\Form\Radio( 'widget_feed_comment_status_visible', isset( $this->configuration['widget_feed_comment_status_visible'] ) ? $this->configuration['widget_feed_comment_status_visible'] : 'any', FALSE, array( 'options' => $types ) ) );
		}
		
		/* Author */
		$author = NULL;
		try
		{
			if ( isset( $this->configuration['widget_feed_item_author'] ) and is_array( $this->configuration['widget_feed_item_author'] ) )
			{
				foreach( $this->configuration['widget_feed_item_author']  as $id )
				{
					$author[ $id ] = \IPS\Member::load( $id );
				}
			}
		}
		catch( \OutOfRangeException $ex ) { }
		$form->add( new \IPS\Helpers\Form\Member( 'widget_feed_item_author', $author, FALSE, array( 'multiple' => true ) ) );
		
		/* Minimum comments/reviews */
		if ( isset( $class::$commentClass ) )
		{
			if ( $class::$firstCommentRequired )
			{
				$form->add( new \IPS\Helpers\Form\Number( 'widget_feed_item_min_posts', isset( $this->configuration['widget_feed_item_min_posts'] ) ? $this->configuration['widget_feed_item_min_posts'] : 0, FALSE, array( 'unlimitedLang' => 'any', 'unlimited' => 0 ) ) );
			}
			else
			{
				$form->add( new \IPS\Helpers\Form\Number( 'widget_feed_item_min_comments', isset( $this->configuration['widget_feed_item_min_comments'] ) ? $this->configuration['widget_feed_item_min_comments'] : 0, FALSE, array( 'unlimitedLang' => 'any', 'unlimited' => 0 ) ) );
			}
		}
		if ( isset( $class::$reviewClass ) )
		{
			$form->add( new \IPS\Helpers\Form\Number( 'widget_feed_item_min_reviews', isset( $this->configuration['widget_feed_item_min_reviews'] ) ? $this->configuration['widget_feed_item_min_reviews'] : 0, FALSE, array( 'unlimitedLang' => 'any', 'unlimited' => 0 ) ) );
		}
		
		/* Rating */
		if ( in_array( 'IPS\Content\Ratings', class_implements( $class ) ) and isset( $class::$databaseColumnMap['rating_average'] ) )
		{
			$form->add( new \IPS\Helpers\Form\Rating( 'widget_feed_item_min_rating', isset( $this->configuration['widget_feed_item_min_rating'] ) ? $this->configuration['widget_feed_item_min_rating'] : 0, FALSE, array() ) );
		}

		if ( isset( $class::$databaseColumnMap['date'] ) )
		{
			$form->add( new \IPS\Helpers\Form\Select( 'widget_feed_comment_date', isset( $this->configuration['widget_feed_comment_date'] ) ? $this->configuration['widget_feed_comment_date'] : '0', FALSE, array(
				'options' => array(
					0	=> 'show_all',
					1	=> 'today',
					5	=> 'last_5_days',
					7	=> 'last_7_days',
					10	=> 'last_10_days',
					15	=> 'last_15_days',
					20	=> 'last_20_days',
					25	=> 'last_25_days',
					30	=> 'last_30_days',
					60	=> 'last_60_days',
					90	=> 'last_90_days',
				)
			) ) );
		}

		/* Number to show */
 		$form->add( new \IPS\Helpers\Form\Number( 'widget_feed_show', isset( $this->configuration['widget_feed_show'] ) ? $this->configuration['widget_feed_show'] : 5, TRUE ) );
 		
 		/* Sort */
		$form->add( new \IPS\Helpers\Form\Select( 'widget_feed_sort_dir', isset( $this->configuration['widget_feed_sort_dir'] ) ? $this->configuration['widget_feed_sort_dir'] : 'desc', FALSE, array(
            'options' => array(
	            'desc'   => 'descending',
	            'asc'    => 'ascending'
            )
        ) ) );
        
        /* Fix up some language strings */
        $langs = array( 'widget_feed_item_status_locked', 'widget_feed_item_status_pinned', 'widget_feed_item_status_featured', 'widget_feed_comment_status_visible', 'widget_feed_item_author', 'widget_feed_item_min_posts', 'widget_feed_item_min_comments', 'widget_feed_item_min_reviews', 'widget_feed_item_min_rating' );
		$words = \IPS\Member::loggedIn()->language()->get( $langs );
		
        foreach( $langs as $lang )
	    {
	   		\IPS\Member::loggedIn()->language()->words[ $lang ] = sprintf( $words[ $lang ], \IPS\Member::loggedIn()->language()->addToStack( $itemClass::$title ) );
	    }
        
 		return $form;
 	}
 	
 	/**
 	 * Ran before saving widget configuration
 	 *
 	 * @param	array	$values	Values from form
 	 * @return	array
 	 */
 	public function preConfig( $values )
 	{
	 	$class     = static::$class;
		$itemClass = $class::$itemClass;
	 	
 		if ( is_array( $values[ 'widget_feed_container_' . $itemClass::$title ] ) )
 		{
	 		$values['widget_feed_container'] = array_keys( $values[ 'widget_feed_container_' . $itemClass::$title ] );
			unset( $values[ 'widget_feed_container_' . $class::$title ] );
 		}
 		
 		if ( is_array( $values['widget_feed_item_author'] ) )
 		{
	 		$members = array();
	 		foreach( $values['widget_feed_item_author'] as $member )
	 		{
		 		$members[] = $member->member_id;
	 		}
	 		
	 		$values['widget_feed_item_author'] = $members;
 		}
 		
 		return $values;
 	}
 	
 	/**
	 * Get where clause
	 *
	 * @return	array
	 */
	protected function buildWhere()
	{
		$class     = static::$class;
		$itemClass = $class::$itemClass;
		$where     = array();
				
		/* Container */
		if ( isset( $itemClass::$containerNodeClass ) and !empty( $this->configuration['widget_feed_container'] ) )
		{
			$where['item'][] = array( \IPS\Db::i()->in( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['container'], $this->configuration['widget_feed_container'] ) );
		}
		
		/* Locked or open status */
		if ( isset( $this->configuration['widget_feed_item_status_locked'] ) and in_array( 'IPS\Content\Lockable', class_implements( $itemClass ) ) )
		{
			if ( $this->configuration['widget_feed_item_status_locked'] == 'closed' )
			{
				$where['item'][] = isset( $itemClass::$databaseColumnMap['locked'] ) ? array( $itemClass::$databaseTable . '.' .  $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['locked'] . '=?', 1 ) : array( $itemClass::$databaseTable . '.' .  $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['status'] . '=?', 'closed' );
			}
			elseif ( $this->configuration['widget_feed_item_status_locked'] == 'open' )
			{
				$where['item'][] = isset( $itemClass::$databaseColumnMap['locked'] ) ? array( $itemClass::$databaseTable . '.' .  $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['locked'] . '=?', 0 ) : array( $itemClass::$databaseTable . '.' .  $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['status'] . '=?', 'open' );
			}
		}

		/* Featured or unfeatured */
		if ( isset( $this->configuration['widget_feed_item_status_featured'] ) and in_array( 'IPS\Content\Featurable', class_implements( $itemClass ) ) )
		{
			if ( $this->configuration['widget_feed_item_status_featured'] == 'notfeatured' )
			{
				$where['item'][] = array( $itemClass::$databaseTable . '.' .  $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['featured'] . '=0' );
			}
			elseif ( $this->configuration['widget_feed_item_status_featured'] == 'featured' )
			{
				$where['item'][] = array( $itemClass::$databaseTable . '.' .  $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['featured'] . '=1' );
			}
		}

		/* Pinned or unpinned */
		if ( isset( $this->configuration['widget_feed_item_status_pinned'] ) and in_array( 'IPS\Content\Pinnable', class_implements( $itemClass ) ) )
		{
			if ( $this->configuration['widget_feed_item_status_pinned'] == 'notpinned' )
			{
				$where['item'][] = array( $itemClass::$databaseTable . '.' .  $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['pinned'] . '=0' );
			}
			elseif ( $this->configuration['widget_feed_item_status_pinned'] == 'pinned' )
			{
				$where['item'][] = array( $itemClass::$databaseTable . '.' .  $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['pinned'] . '=1' );
			}
		}
		
		/* Author */
		if ( isset( $this->configuration['widget_feed_item_author'] ) and is_array( $this->configuration['widget_feed_item_author'] ) and count( $this->configuration['widget_feed_item_author'] ) )
		{
			$where['item'][] = array( \IPS\Db::i()->in( $itemClass::$databaseTable . '.' .  $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['author'], $this->configuration['widget_feed_item_author'] ) );
		}
		
		/* Min posts/comments/reviews */
		if ( isset( $this->configuration['widget_feed_item_min_posts'] ) and isset( $this->configuration['widget_feed_item_min_posts'] ) and isset( $this->configuration['widget_feed_item_min_posts'] ) )
		{
			$where['item'][] = array( $itemClass::$databaseTable . '.' .  $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['num_comments'] . '>?', (int) $this->configuration['widget_feed_item_min_posts'] );
		}
		if ( isset( $this->configuration['widget_feed_item_min_comments'] ) and isset( $this->configuration['widget_feed_item_min_comments'] ) and $this->configuration['widget_feed_item_min_comments'] )
		{
			$where['item'][] = array( $itemClass::$databaseTable . '.' .  $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['num_comments'] . '>?', (int) $this->configuration['widget_feed_item_min_comments'] );
		}
		if ( isset( $this->configuration['widget_feed_item_min_reviews'] ) and isset( $this->configuration['widget_feed_item_min_reviews'] ) and $this->configuration['widget_feed_item_min_reviews'] )
		{
			$where['item'][] = array( $itemClass::$databaseTable . '.' .  $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['num_reviews'] . '>?', (int) $this->configuration['widget_feed_item_min_reviews'] );
		}
		
		/* Rating */
		if ( isset( $this->configuration['widget_feed_item_min_rating'] ) and isset( $this->configuration['widget_feed_item_min_rating'] ) and $this->configuration['widget_feed_item_min_rating'] )
		{
			$where['item'][] = array( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['rating_average'] . '>?', (int) $this->configuration['widget_feed_item_min_rating'] );
		}

		/* Start date */
		if ( isset( $this->configuration['widget_feed_comment_date'] ) and isset( $this->configuration['widget_feed_comment_date'] ) and $this->configuration['widget_feed_comment_date'] > 0 )
		{
			$where[] = array( $class::$databaseTable . '.' .  $class::$databasePrefix . $class::$databaseColumnMap['date'] . '>?',  \IPS\DateTime::create()->sub( new \DateInterval( 'P' . $this->configuration['widget_feed_comment_date'] . 'D' ) )->getTimestamp() );
		}

		return $where;
	}

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		$class = static::$class;

		/* What visible status are we checking? */
		$hidden	= \IPS\Content\Hideable::FILTER_AUTOMATIC;

		if( isset( $this->configuration['widget_feed_comment_status_visible'] ) )
		{
			switch( $this->configuration['widget_feed_comment_status_visible'] )
			{
				case 'visible':
					$hidden	= \IPS\Content\Hideable::FILTER_PUBLIC_ONLY;
				break;

				case 'hidden':
					$hidden	= \IPS\Content\Hideable::FILTER_ONLY_HIDDEN;
				break;
			}
		}

		$items = $class::getItemsWithPermission(
			$this->buildWhere(),
			( isset( $this->configuration['widget_feed_sort_on'] ) and isset( $this->configuration['widget_feed_sort_dir'] ) ) ? ( $class::$databasePrefix . $class::$databaseColumnMap['date'] . ' ' . $this->configuration['widget_feed_sort_dir'] ) : NULL,
			( isset( $this->configuration['widget_feed_show'] ) AND $this->configuration['widget_feed_show'] ) ? $this->configuration['widget_feed_show'] : 5,
			( !isset( $this->configuration['widget_feed_use_perms'] ) or $this->configuration['widget_feed_use_perms'] ) ? 'read' : NULL,
			$hidden
		);
				
		if ( count( $items ) )
		{
			return $this->output( $items, isset( $this->configuration['widget_feed_title'] ) ? $this->configuration['widget_feed_title'] : \IPS\Member::loggedIn()->language()->addToStack( $class::$title . '_pl' ) );
		}
		else
		{
			return '';
		}
	}
}