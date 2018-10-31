<?php

namespace Prodigious\Sonata\MenuBundle\Manager;

use Doctrine\ORM\EntityManager;
use Prodigious\Sonata\MenuBundle\Repository\MenuRepository;
use Prodigious\Sonata\MenuBundle\Repository\MenuitemRepository;
use Prodigious\Sonata\MenuBundle\Entity\Menu;
use Prodigious\Sonata\MenuBundle\Entity\MenuInterface;
use Prodigious\Sonata\MenuBundle\Entity\MenuItem;

/**
 * Menu manager
 */
class MenuManager
{
    const STATUS_ENABLED = true;
    const STATUS_DISABLED = false;
    const STATUS_ALL = null;

    const ITEM_ROOT = true;
    const ITEM_CHILD = false;
    const ITEM_ALL = null;

    /**
     *
     * @var EntityManager
     */
    protected $em;

    /**
     * @var MenuRepository
     */
    protected $menuRepository;

    /**
     * @var MenuItemRepository
     */
    protected $menuItemRepository;

    /**
     * Constructor
     *
     * @param EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
        $this->menuRepository = $em->getRepository(Menu::class);
        $this->menuItemRepository = $em->getRepository(MenuItem::class);
    }

    /**
     * Load menu by id
     *
     * @param int $id
     * @return Menu
     */
    public function load($id)
    {
        $menu = $this->menuRepository->findById($id);

        return $menu;
    }

    /**
     * Load menu by alias
     *
     * @param string $alias
     * @return Menu
     */
    public function loadByAlias($alias)
    {
        $menu = $this->menuRepository->findOneByAlias($alias);

        return $menu;
    }

    /**
     * Remove a menu
     *
     * @param mixed $menu
     */
    public function remove($menu)
    {
        $menu = $this->menuRepository->remove($menu);
    }

    /**
     * Save a menu
     *
     * @param Menu $menu
     */
    public function save(Menu $menu)
    {
        $this->menuRepository->save($menu);
    }

    /**
     * @return Menu[]
     */
    public function findAll()
    {
        return $this->menuRepository->findAll();
    }

    /**
     * Get first level menu items
     *
     * @param Menu $menu
     * @return MenuItems[]
     */
    public function getRootItems(Menu $menu, $status)
    {
        return $this->getMenuItems($menu, static::ITEM_ROOT, $status);
    }

    /**
     * Get enabled menu items
     *
     * @param Menu $menu
     * @return MenuItems[]
     */
    public function getEnabledItems(Menu $menu)
    {
        return $this->getMenuItems($menu, static::ITEM_ALL, static::STATUS_ENABLED);
    }

    /**
     * Get disabled menu items
     *
     * @param Menu $menu
     * @return MenuItems[]
     */
    public function getDisabledItems(Menu $menu)
    {
        return $this->getMenuItems($menu, static::ITEM_ALL, static::STATUS_DISABLED);
    }

    /**
     * Get menu items
     *
     * @return MenuItem[]
     */
    public function getMenuItems(Menu $menu, $root = self::ALL_ELEMENTS, $status = self::STATUS_ALL)
    {
        $menuItems = $menu->getMenuItems()->toArray();

        return array_filter($menuItems, function(MenuItem $menuItem) use ($root, $status) {
            // Check root parameter
            if ($root === static::ITEM_ROOT && null !== $menuItem->getParent()
             || $root === static::ITEM_CHILD && null === $menuItem->getParent()
            ) {
                return;
            }

            // Check status parameter
            if ($status === static::STATUS_ENABLED && !$menuItem->getEnabled()
             || $status === static::STATUS_DISABLED && $menuItem->getEnabled()
            ) {
                return;
            }

            return $menuItem;
        });
    }

    /**
     * Update menu tree
     *
     * @param mixed $menu
     * @param array $items
     *
     * @return bool
     */
    public function updateMenuTree($menu, $items, $parent=null)
    {
        $update = false;

        if(!($menu instanceof MenuInterface)) {
            $menu = $this->load($menu);
        }

        if(!empty($items) && $menu) {

            foreach ($items as $pos => $item) {
                /** @var MenuItem $menuItem */
                $menuItem = $this->menuItemRepository->findOneBy(array('id' => $item->id, 'menu' => $menu));

                if($menuItem) {
                    $menuItem
                        ->setPosition($pos)
                        ->setParent($parent)
                    ;

                    $this->em->persist($menuItem);
                }

                if(isset($item->children) && !empty($item->children)) {
                    $this->updateMenuTree($menu, $item->children, $menuItem);
                }
            }

            $this->em->flush();

            $update = true;
        }

        return $update;
    }

}
