# Pseudocode Guide

How to write pseudocode for module generation.

## Pseudocode Structure

```
start: milkadmin/Modules/AIPseudocode/module-instructions/start.md

Module: {ModuleName}
Access: {authorized users / public / registered users}
Menu: {menu description}
Header links: {link1, link2}

Table: {table_name}
Fields:
- {field description}
- {field description}
```

## First Line (Required)

```
start: milkadmin/Modules/AIPseudocode/module-instructions/start.md
```

This tells AI where to find instructions and examples.

## Module Section

**Module name:**
```
Module: Products
Module: TestList
```

**Access level (optional, default: authorized):**
```
Access: authorized users
Access: public
Access: registered users only
Access: admin
```

**Menu (optional):**
```
Menu: sidebar with Products and Categories
Menu: Products, Settings, Reports
Header links: New Product, Import
```

## Table Section

**Table name:**
```
Table: products
Table: test_list
```


**Fields (optional if table exists):**
```
Fields:
- name (string)
- description (text)
- price (decimal)
- stock quantity (integer)
- active (boolean)
- created date
```

## Examples

### Minimal (table exists)
```
start: milkadmin/Modules/AIPseudocode/module-instructions/start.md

Module: Products
Table: products
```

### Basic with menu
```
start: milkadmin/Modules/AIPseudocode/module-instructions/start.md

Module: Products
Access: authorized users
Menu: Products, Categories
Table: products
```

### Complete with fields
```
start: milkadmin/Modules/AIPseudocode/module-instructions/start.md

Module: Products
Access: authorized users
Menu: All Products, Categories, Settings
Header links: New Product, Import

Table: products
Fields:
- name (required)
- description
- price
- stock quantity
- active status
```



Keep it minimal - AI infers details from start.md and examples.
