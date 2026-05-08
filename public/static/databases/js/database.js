define(['table','form'], function (Table,Form) {
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                searchName:'TABLE_NAME',
                requests:{
                    index_url:'databases/database/index',
                    edit_url:'databases/database/edit',
                    list_url:'databases/database/list',
                    destroy_url:'databases/database/destroy',
                    recycle_url:'databases/database/recycle',
                    import_url:'databases/database/import',
                    export_url:'databases/database/export',
                    modify_url:'databases/database/modify',
                    delete_url:{
                        type: 'delete',
                        event: 'request',
                        class: 'layui-btn layui-btn-danger',
                        icon: 'layui-icon layui-icon-delete',
                        text: __('删除'),
                        title: __('删除表'),
                        url: 'databases/database/delete',
                    },
                    logs_url:{
                        type: 'open',
                        event: 'open',
                        class: 'layui-btn layui-btn-warm',
                        text: __('查看'),
                        title: __('查看'),
                        url: 'databases/table/index',
                        icon: 'layui-icon layui-icon-log',
                        extend: "data-btn='false'",
                        width: '1200',
                        height: '800',
                    },
                    run_url:{
                        type: 'request',
                        event: 'request',
                        class: 'layui-btn layui-btn-normal',
                        text: __('备份'),
                        title: __('备份数据'),
                        url: 'databases/database/beifen',
                        icon: 'layui-icon layui-icon-play',
                    },
                    clear_url:{
                        type: 'request',
                        event: 'request',
                        class: 'layui-btn layui-btn-blue',
                        text: __('更新行数'),
                        title: __('更新行数'),
                        url: 'databases/database/dataclear',
                        icon: 'layui-icon layui-icon-play',
                    }

                }
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','export','run_url','clear_url'],
                cols: [[
                    {checkbox: true,},
                    {field:'TABLE_NAME', title: __('Tablename'),align: 'left',sort:'sort',},
                    {field:'ENGINE', title: __('Engine'),align: 'center',sort:'sort',search: false,width: 100,},
                    {field:'TABLE_ROWS', title: __('Rows'),align: 'center',sort:'sort',search: false,width: 100,},
                    {field:'TABLE_COMMENT', title: __('Explain'),align: 'left',sort:'sort',},
                    {field:'TABLE_COLLATION', title: __('Collation'),align: 'center',sort:'sort',search: false,width: 190,},
                    {field:'CREATE_TIME',title: __('CreateTime'),align: 'center',timeType:'datetime',dateformat:'yyyy-MM-dd HH:mm:ss',searchdateformat:'yyyy-MM-dd HH:mm:ss',search:false,sort:false,width: 170},
                    {field:'UPDATE_TIME',title: __('UpdateTime'),align: 'center',timeType:'datetime',dateformat:'yyyy-MM-dd HH:mm:ss',searchdateformat:'yyyy-MM-dd HH:mm:ss',search:false,sort:true,width: 170},
                    {
                        fixed:"right",
                        width: 260,
                        align: "center",
                        title: __("Operat"),
                        init: Table.init,
                        templet: Table.templet.operat,
                        operat: ["list","edit", "logs_url","run_url"]
                    },
                ]],
                limits: [10, 15, 20, 25, 50, 100,500],
                limit: 15,
                page: false,
                done: function (res, curr, count) {
                }
            });

            var laydate = layui.laydate;
            laydate.render({elem: '#field_time_min'});
            laydate.render({elem: '#field_time_max'});
            laydate.render({elem: '#field_lastdt_min'});
            laydate.render({elem: '#field_lastdt_max'});
            laydate.render({elem: '#field_startdt_min'});
            laydate.render({elem: '#field_startdt_max'});
            laydate.render({elem: '#field_create_time_min'});
            laydate.render({elem: '#field_create_time_max'});
            laydate.render({elem: '#field_update_time_min'});
            laydate.render({elem: '#field_update_time_max'});
//引入layuidate方法
            Table.api.bindEvent(Table.init.tableId);

        },
        add: function () {
            Controller.api.bindevent()
        },
        edit: function () {
            Controller.api.bindevent()
        },
        recycle: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests: {
                    delete_url:'addons/database/backend/database/delete',
                    recycle_url:'addons/database/backend/database/recycle',
                    restore_url:'addons/database/backend/database/restore',

                },
            };
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.recycle_url),
                init: Table.init,
                toolbar: ['refresh','delete','restore'],
                cols: [[
                    {checkbox: true,},
                    {field:'TABLE_NAME', title: __('Tablename'),align: 'center',sort:'sort',width: 120,},
                    {field:'ENGINE', title: __('Engine'),align: 'center',sort:'sort',width: 120,search: false},
                    {field:'TABLE_ROWS', title: __('Rows'),align: 'center',sort:'sort',width: 120,search: false,},
                    {field:'TABLE_COMMENT', title: __('Explain'),align: 'center',sort:'sort',width: 120,},
                    {field:'TABLE_COLLATION', title: __('Collation'),align: 'center',sort:'sort',width: 120,search: false,},
                    {field:'CREATE_TIME',title: __('CreateTime'),align: 'center',timeType:'datetime',dateformat:'yyyy-MM-dd HH:mm:ss',searchdateformat:'yyyy-MM-dd HH:mm:ss',search:false,sort:false,width: 170},
                    {field:'UPDATE_TIME',title: __('UpdateTime'),align: 'center',timeType:'datetime',dateformat:'yyyy-MM-dd HH:mm:ss',searchdateformat:'yyyy-MM-dd HH:mm:ss',search:false,sort:true,width: 170},
                    {
                        fixed:"right",
                        minWidth: 250,
                        width: 200,
                        align: "center",
                        title: __("Operat"),
                        init: Table.init,
                        templet: Table.templet.operat,
                        operat: ["list", "logs_url","run_url"]
                    },
                ]],
                limits: [10, 15, 20, 25, 50, 100,500],
                limit: 15,
                page: true,
                done: function (res, curr, count) {
                }
            });
            Table.api.bindEvent(Table.init.tableId);

        },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }
    };
    return Controller;
});