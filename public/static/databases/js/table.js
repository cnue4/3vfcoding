define(['table','form'], function (Table,Form) {
    let Controller = {
        columns: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                searchName:'TABLE_NAME',
                requests:{
                    index_url:'databases/table/columns?id=',
                    destroy_url:'databases/table/destroy',
                    recycle_url:'databases/table/recycle',
                    import_url:'databases/table/import',
                    export_url:'databases/table/export',
                    edit_url:'databases/table/edit',
                    modify_url:'databases/table/modify',
                    delete_url:{
                        type: 'delete',
                        event: 'request',
                        class: 'layui-btn layui-btn-danger',
                        icon: 'layui-icon layui-icon-delete',
                        text: __('删除'),
                        title: __('删除列'),
                        url: 'databases/table/delete',
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
                    }

                }
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','export'],
                cols: [[
                    {checkbox: true,},
                    {title:'序号',type:'numbers',width: 70,},
                    {field:'COLUMN_NAME', title: __('列名'),align: 'left',sort:'sort',width: 190,},
                    {field:'id', title: __('列名'),align: 'left',sort:'sort',width: 190,hide:true},
                    {field:'IS_NULLABLE', title: __('是否必填'),align: 'center',sort:'sort',width: 120,search: false,edit:false,selectList: {'YES': __('是'), 'NO': __('否')}},
                    {field:'DATA_TYPE', title: __('数据类型'),sort:'sort',width: 150,search: false},
                    {field:'CHARACTER_MAXIMUM_LENGTH', title: __('长度'),align: 'left',sort:'sort',width: 120,edit:true,},
                    // {
                    //     fixed:"DATA_TYPE",
                    //     width: 130,
                    //     align: "center",
                    //     selectList:selectList,
                    //     title: "数据类型",
                    //     templet: Table.templet.selectssss,
                    // },
                    {field:'COLUMN_COMMENT', title: __('备注'),align: 'left',sort:'sort',width: 240,search: false,edit:true,},
                    {
                        fixed:"right",
                        width: 130,
                        align: "center",
                        title: __("Operat"),
                        init: Table.init,
                        templet: Table.templet.operat,
                        operat: ["delete_url"]
                    },
                ]],
                limits: [10, 15, 20, 25, 50, 100,500],
                limit: 15,
                page: false,
                done: function (res, curr, count) {
                    res.data.forEach(function (item, index) {//根据已有的值回填下拉框
                        layui.each($("select[name='datatype']", ""), function (index, item) {var elem =$(item);
                            elem.next().children().children()[0].defaultValue = elem.data('value');//elem.val(elem.data('value'));
                        });
                        table.render('select');
                    });
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
                    delete_url:'addons/table/backend/table/delete',
                    recycle_url:'addons/table/backend/table/recycle',
                    restore_url:'addons/table/backend/table/restore',

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