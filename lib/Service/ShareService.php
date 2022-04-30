<?php

namespace OCA\Tables\Service;

use DateTime;
use Exception;

use OCA\Tables\Db\Share;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use Psr\Log\LoggerInterface;

class ShareService extends SuperService {

    protected $mapper;

    /** @var TableMapper */
    protected $tableMapper;

	public function __construct(PermissionsService $permissionsService, LoggerInterface $logger, $userId,
    ShareMapper $shareMapper, TableMapper $tableMapper) {
        parent::__construct($logger, $userId, $permissionsService);
        $this->mapper = $shareMapper;
        $this->tableMapper = $tableMapper;
	}


    /**
     * @throws InternalError
     */
    public function findAll(): array {
        try {
            return $this->mapper->findAll($this->userId);
        } catch (\OCP\DB\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new InternalError($e->getMessage());
        }
    }


    /**
     * @throws PermissionError
     * @throws NotFoundError
     * @throws InternalError
     */
    public function find($id) {
        // TODO
		try {
			$item = $this->mapper->find($id);

            // security
            if(!$this->permissionsService->canReadShare($item))
                throw new PermissionError('PermissionError: can not read share with id '.$id);

            return $item;
        } catch (DoesNotExistException $e) {
            $this->logger->warning($e->getMessage());
            throw new NotFoundError($e->getMessage());
        } catch (MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new InternalError($e->getMessage());
        }
    }


    /**
     * @throws InternalError
     */
    public function findTablesSharedWithMe(): array
    {
        $returnArray = [];
        try {
            $tablesSharedWithMe = $this->mapper->findAllSharesFor('table', $this->userId);
        } catch (\OCP\DB\Exception $e) {
            throw new InternalError($e->getMessage());
        }
        foreach ($tablesSharedWithMe as $share) {
            try {
                $table = $this->tableMapper->find($share->getNodeId());
                /** @noinspection PhpUndefinedMethodInspection */
                $table->setIsShared(true);
                /** @noinspection PhpUndefinedMethodInspection */
                $table->setOnSharePermissions([
                    'read'  => $share->getPermissionRead(),
                    'create'  => $share->getPermissionCreate(),
                    'update'  => $share->getPermissionUpdate(),
                    'delete'  => $share->getPermissionDelete(),
                    'manage'  => $share->getPermissionManage(),
                ]);
            } catch (DoesNotExistException|\OCP\DB\Exception|MultipleObjectsReturnedException $e) {
                throw new InternalError($e->getMessage());
            }
            $returnArray[] = $table;
        }
        return $returnArray;
    }

    /**
     * @noinspection PhpUndefinedMethodInspection
     *
     * @throws \OCP\DB\Exception
     * @throws InternalError
     */
    public function create($nodeId, $nodeType, $user, $permissionRead, $permissionCreate, $permissionUpdate, $permissionDelete, $permissionManage) {
        $time = new DateTime();
		$item = new Share();
        $item->setUserSender($this->userId);
        $item->setUserReceiver($user);
        $item->setNodeId($nodeId);
        $item->setNodeType($nodeType);
        $item->setPermissionRead($permissionRead);
        $item->setPermissionCreate($permissionCreate);
        $item->setPermissionUpdate($permissionUpdate);
        $item->setPermissionDelete($permissionDelete);
        $item->setPermissionManage($permissionManage);
        $item->setCreatedAt($time->format('Y-m-d H:i:s'));
        $item->setLastEditAt($time->format('Y-m-d H:i:s'));
        try {
            $newShare = $this->mapper->insert($item);
        } catch (\OCP\DB\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new InternalError($e->getMessage());
        }
        return $newShare;
	}

    /**
     * @noinspection PhpUndefinedMethodInspection
     *
     * @throws InternalError
     */
    public function update($id, $permissionRead, $permissionCreate, $permissionUpdate, $permissionDelete, $permissionManage) {
		try {
            $item = $this->mapper->find($id);

            // security
            if(!$this->permissionsService->canUpdateShare($item))
                throw new PermissionError('PermissionError: can not update share with id '.$id);

            $userId = $this->userId;
            $time = new DateTime();
            $item = new Table();
            $item->setUser($userId);
            $item->setPermissionRead($permissionRead);
            $item->setPermissionCreate($permissionCreate);
            $item->setPermissionUpdate($permissionUpdate);
            $item->setPermissionDelete($permissionDelete);
            $item->setPermissionManage($permissionManage);
            $item->setLastEditAt($time->format('Y-m-d H:i:s'));

			return $this->mapper->update($item);
		} catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw new InternalError($e->getMessage());
		}
	}

    /**
     * @throws InternalError
     */
    public function delete($id) {
        // TODO
		try {
            $item = $this->mapper->find($id);

            // security
            if(!$this->permissionsService->canDeleteShare($item))
                throw new PermissionError('PermissionError: can not delete share with id '.$id);

            $this->rowService->deleteAllByTable($id);
            $columns = $this->columnService->findAllByTable($id);
            foreach ($columns as $column) {
                $this->columnService->delete($column->id, true);
            }
			$this->mapper->delete($item);
			return $item;
		} catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw new InternalError($e->getMessage());
        }
    }
}
