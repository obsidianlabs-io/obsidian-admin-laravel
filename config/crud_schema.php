<?php

declare(strict_types=1);

return [
    'resources' => [
        'user' => [
            'resource' => 'user',
            'permission' => 'user.view',
            'searchFields' => [
                [
                    'key' => 'userName',
                    'type' => 'input',
                    'labelKey' => 'page.user.userName',
                    'placeholderKey' => 'page.user.userNamePlaceholder',
                    'clearable' => true,
                ],
                [
                    'key' => 'userEmail',
                    'type' => 'input',
                    'labelKey' => 'common.email',
                    'placeholderKey' => 'page.user.emailPlaceholder',
                    'clearable' => true,
                ],
                [
                    'key' => 'roleCode',
                    'type' => 'select',
                    'labelKey' => 'common.role',
                    'placeholderKey' => 'common.selectRole',
                    'clearable' => true,
                    'filterable' => true,
                    'optionSource' => 'role.all',
                ],
                [
                    'key' => 'status',
                    'type' => 'select',
                    'labelKey' => 'common.status',
                    'placeholderKey' => 'common.selectStatus',
                    'clearable' => true,
                    'optionSource' => 'status.enable',
                ],
            ],
            'columns' => [
                [
                    'key' => 'index',
                    'type' => 'index',
                    'titleKey' => 'common.index',
                    'align' => 'center',
                    'width' => 64,
                ],
                [
                    'key' => 'userName',
                    'type' => 'text',
                    'titleKey' => 'page.user.userName',
                    'align' => 'center',
                    'minWidth' => 120,
                ],
                [
                    'key' => 'email',
                    'type' => 'text',
                    'titleKey' => 'common.email',
                    'align' => 'center',
                    'minWidth' => 220,
                ],
                [
                    'key' => 'roleName',
                    'type' => 'text',
                    'titleKey' => 'common.role',
                    'align' => 'center',
                    'minWidth' => 140,
                    'emptyLabelKey' => 'common.noData',
                ],
                [
                    'key' => 'status',
                    'type' => 'status',
                    'titleKey' => 'common.status',
                    'align' => 'center',
                    'width' => 120,
                ],
                [
                    'key' => 'createTime',
                    'type' => 'datetime',
                    'titleKey' => 'common.createdAt',
                    'align' => 'center',
                    'width' => 180,
                ],
                [
                    'key' => 'updateTime',
                    'type' => 'datetime',
                    'titleKey' => 'common.updatedAt',
                    'align' => 'center',
                    'width' => 180,
                ],
            ],
            'scrollX' => 1300,
        ],
    ],
];
