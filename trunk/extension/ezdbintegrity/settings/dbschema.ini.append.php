<?php /*

[FKSettings]
# List of FK relationships in the DB
# Format: table_name[]=col1,col2,colx::parent_table::cola,colb,colc

ezcontentclass[]=creator_id::ezuser::contentobject_id
ezcontentclass[]=modifier_id::ezuser::contentobject_id

# needs version too?
ezcontentclass_attribute[]=contentclass_id::ezcontentclass::id

ezcontentobject[]=contentclass_id::ezcontentclass::id

ezcontentobject_tree[]=contentobject_id::ezcontentobject::id

ezpreferences[]=
ezuser[]=contentobject_id::ezcontentobject::id

