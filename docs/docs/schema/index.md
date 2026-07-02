---
title: Schema
sidebar_position: 7
---

# Schema

Schema classes organize the layout of a resource's form — grouping fields into sections and tabs.

- [Sections](sections) — Group fields under a heading, optionally in a grid, optionally collapsible.
- [Tabs](tabs) — Split a long form into switchable tab panels.

Both types drop directly into `Form::schema()` alongside (or instead of) bare field instances. Forge's form renderer knows how to handle them; validation and save logic flatten through them automatically.
