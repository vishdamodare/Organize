<?php

namespace Espo\Modules\Jewellery\Classes\Acl;

use Espo\Core\Acl\AccessEntityCREDChecker;
use Espo\Core\Acl\ScopeData;
use Espo\Core\Acl\Table;
use Espo\Core\AclManager;
use Espo\Entities\User;
use Espo\Modules\Jewellery\Tools\Totals;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Quotation/order line items have no owner of their own: access to an item is
 * exactly access to its parent document. Creating, editing or deleting an item
 * requires *edit* access to the parent; reading requires *read* access.
 */
class ItemAccessChecker implements AccessEntityCREDChecker
{
    protected string $parentType = 'Quotation';

    public function __construct(
        private AclManager $aclManager,
        private EntityManager $entityManager,
    ) {}

    public function check(User $user, ScopeData $data): bool
    {
        return $this->checkRead($user, $data);
    }

    public function checkCreate(User $user, ScopeData $data): bool
    {
        return $this->aclManager->checkScope($user, $this->parentType, Table::ACTION_EDIT);
    }

    public function checkRead(User $user, ScopeData $data): bool
    {
        return $this->aclManager->checkScope($user, $this->parentType, Table::ACTION_READ);
    }

    public function checkEdit(User $user, ScopeData $data): bool
    {
        return $this->checkCreate($user, $data);
    }

    public function checkDelete(User $user, ScopeData $data): bool
    {
        return $this->checkCreate($user, $data);
    }

    public function checkEntityCreate(User $user, Entity $entity, ScopeData $data): bool
    {
        return $this->checkParent($user, $entity, Table::ACTION_EDIT);
    }

    public function checkEntityRead(User $user, Entity $entity, ScopeData $data): bool
    {
        return $this->checkParent($user, $entity, Table::ACTION_READ);
    }

    public function checkEntityEdit(User $user, Entity $entity, ScopeData $data): bool
    {
        return $this->checkParent($user, $entity, Table::ACTION_EDIT);
    }

    public function checkEntityDelete(User $user, Entity $entity, ScopeData $data): bool
    {
        return $this->checkParent($user, $entity, Table::ACTION_EDIT);
    }

    private function checkParent(User $user, Entity $item, string $action): bool
    {
        [$parentType, $attr] = Totals::ITEM_PARENT[$item->getEntityType()];
        $parentId = $item->get($attr);
        $parent = $parentId ? $this->entityManager->getEntityById($parentType, $parentId) : null;

        return $parent !== null && $this->aclManager->checkEntity($user, $parent, $action);
    }
}
