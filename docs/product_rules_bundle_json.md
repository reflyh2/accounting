# PRODUCT_RULES_BUNDLE.json.md

## Consolidated Product Rules Bundle (Runtime Artifact)

This file merges, into a single JSON payload:
- Product type templates (UI + behavior presets)
- Product kind seeds (default cost models)
- Capability × kind matrix
- Full validation rules (all kinds)

Intended usage:
- Load once at boot / cache per tenant
- Use the same artifact for backend + frontend validation and UI rendering

Source docs:
- PRODUCT_TYPE_TEMPLATES.json.md (v2)
- PRODUCT_KIND_SEEDS.md
- PRODUCT_CAPABILITY_MATRIX.md
- PRODUCT_VALIDATION_RULES.json.md (v2)

---

```json
{
  "bundle_version": "2025-12-26",
  "product_taxonomy_version": "PRODUCT.md v2",
  "capabilities": [
    "inventory_tracked",
    "variantable",
    "bookable",
    "rental",
    "serialized",
    "package"
  ],
  "cost_models": [
    "none",
    "inventory_layer",
    "direct_expense_per_sale",
    "job_costing",
    "asset_usage_costing",
    "prepaid_consumption",
    "hybrid"
  ],

  "kinds": [
    "goods_stock",
    "goods_nonstock",
    "consumable",
    "digital_good",
    "bundle",
    "gift_card",
    "service_professional",
    "service_managed",
    "service_labor",
    "service_fee",
    "service_installation",
    "accommodation",
    "venue_booking",
    "event_ticket",
    "tour_activity",
    "appointment",
    "asset_rental",
    "rental_with_operator",
    "lease",
    "air_ticket_resale",
    "train_ticket_resale",
    "bus_ferry_ticket_resale",
    "hotel_resale",
    "travel_package",
    "shipping_charge",
    "insurance_addon",
    "deposit",
    "penalty_fee",
    "membership"
  ],

  "kind_seeds": {
    "goods_stock": {"default_cost_model": "inventory_layer"},
    "goods_nonstock": {"default_cost_model": "direct_expense_per_sale"},
    "consumable": {"default_cost_model": "inventory_layer"},
    "digital_good": {"default_cost_model": "direct_expense_per_sale"},
    "bundle": {"default_cost_model": "hybrid"},
    "gift_card": {"default_cost_model": "none"},

    "service_professional": {"default_cost_model": "job_costing"},
    "service_managed": {"default_cost_model": "hybrid"},
    "service_labor": {"default_cost_model": "job_costing"},
    "service_fee": {"default_cost_model": "direct_expense_per_sale"},
    "service_installation": {"default_cost_model": "job_costing"},

    "accommodation": {"default_cost_model": "hybrid"},
    "venue_booking": {"default_cost_model": "hybrid"},
    "event_ticket": {"default_cost_model": "direct_expense_per_sale"},
    "tour_activity": {"default_cost_model": "hybrid"},
    "appointment": {"default_cost_model": "job_costing"},

    "asset_rental": {"default_cost_model": "asset_usage_costing"},
    "rental_with_operator": {"default_cost_model": "hybrid"},
    "lease": {"default_cost_model": "asset_usage_costing"},

    "air_ticket_resale": {"default_cost_model": "prepaid_consumption"},
    "train_ticket_resale": {"default_cost_model": "direct_expense_per_sale"},
    "bus_ferry_ticket_resale": {"default_cost_model": "direct_expense_per_sale"},
    "hotel_resale": {"default_cost_model": "direct_expense_per_sale"},
    "travel_package": {"default_cost_model": "hybrid"},

    "shipping_charge": {"default_cost_model": "direct_expense_per_sale"},
    "insurance_addon": {"default_cost_model": "direct_expense_per_sale"},
    "deposit": {"default_cost_model": "none"},
    "penalty_fee": {"default_cost_model": "none"},
    "membership": {"default_cost_model": "hybrid"}
  },

  "capability_kind_matrix": {
    "legend": {"allowed": "✅", "optional": "⚠️", "forbidden": "❌", "required": "⭐"},
    "matrix": {
      "goods_stock": {"inventory_tracked": "⭐", "variantable": "⭐", "bookable": "❌", "rental": "❌", "serialized": "⚠️", "package": "⚠️"},
      "goods_nonstock": {"inventory_tracked": "❌", "variantable": "⚠️", "bookable": "❌", "rental": "❌", "serialized": "❌", "package": "⚠️"},
      "consumable": {"inventory_tracked": "⚠️", "variantable": "❌", "bookable": "❌", "rental": "❌", "serialized": "❌", "package": "❌"},
      "digital_good": {"inventory_tracked": "❌", "variantable": "⚠️", "bookable": "❌", "rental": "❌", "serialized": "❌", "package": "⚠️"},
      "bundle": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "❌", "rental": "❌", "serialized": "❌", "package": "⭐"},
      "gift_card": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "❌", "rental": "❌", "serialized": "❌", "package": "❌"},

      "service_professional": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "❌", "rental": "❌", "serialized": "❌", "package": "⚠️"},
      "service_managed": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "❌", "rental": "❌", "serialized": "❌", "package": "⚠️"},
      "service_labor": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "⚠️", "rental": "❌", "serialized": "❌", "package": "❌"},
      "service_fee": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "❌", "rental": "❌", "serialized": "❌", "package": "❌"},
      "service_installation": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "⚠️", "rental": "❌", "serialized": "❌", "package": "❌"},

      "accommodation": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "⭐", "rental": "❌", "serialized": "❌", "package": "❌"},
      "venue_booking": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "⭐", "rental": "❌", "serialized": "❌", "package": "❌"},
      "event_ticket": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "⭐", "rental": "❌", "serialized": "❌", "package": "❌"},
      "tour_activity": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "⭐", "rental": "❌", "serialized": "❌", "package": "⚠️"},
      "appointment": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "⭐", "rental": "❌", "serialized": "❌", "package": "❌"},

      "asset_rental": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "⭐", "rental": "⭐", "serialized": "⭐", "package": "❌"},
      "rental_with_operator": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "⭐", "rental": "⭐", "serialized": "⭐", "package": "❌"},
      "lease": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "⚠️", "rental": "⭐", "serialized": "⭐", "package": "❌"},

      "air_ticket_resale": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "⚠️", "rental": "❌", "serialized": "❌", "package": "⚠️"},
      "train_ticket_resale": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "⚠️", "rental": "❌", "serialized": "❌", "package": "⚠️"},
      "bus_ferry_ticket_resale": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "⚠️", "rental": "❌", "serialized": "❌", "package": "⚠️"},
      "hotel_resale": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "⚠️", "rental": "❌", "serialized": "❌", "package": "⚠️"},
      "travel_package": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "⚠️", "rental": "❌", "serialized": "❌", "package": "⭐"},

      "shipping_charge": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "❌", "rental": "❌", "serialized": "❌", "package": "❌"},
      "insurance_addon": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "❌", "rental": "❌", "serialized": "❌", "package": "❌"},
      "deposit": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "❌", "rental": "❌", "serialized": "❌", "package": "❌"},
      "penalty_fee": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "❌", "rental": "❌", "serialized": "❌", "package": "❌"},
      "membership": {"inventory_tracked": "❌", "variantable": "❌", "bookable": "⚠️", "rental": "❌", "serialized": "❌", "package": "❌"}
    }
  },

  "templates": {
    "schema": {
      "template_code": "string",
      "label": "string",
      "description": "string",
      "defaults": {
        "kind": "product.kind",
        "cost_model": "cost_model",
        "attribute_set_code": "string",
        "capabilities": ["capability"]
      },
      "ui": {
        "wizard": {"enabled": "boolean", "steps": [{"code": "string", "label": "string"}]},
        "sections": {"base": {"fields": ["string"]}, "attributes": {"source": "attribute_set", "mode": "dynamic"}}
      },
      "post_create": [{"action": "string"}]
    },
    "index": [
      "goods_stock_retail",
      "goods_nonstock_basic",
      "consumable_basic",
      "digital_good_basic",
      "bundle_package",
      "gift_card",
      "service_professional",
      "service_managed",
      "service_labor",
      "service_fee",
      "service_installation",
      "accommodation_room_type",
      "venue_booking",
      "event_ticket_resale",
      "tour_activity",
      "appointment",
      "asset_rental_class",
      "rental_with_operator",
      "lease",
      "air_ticket_resale",
      "train_ticket_resale",
      "bus_ferry_ticket_resale",
      "hotel_resale",
      "travel_package",
      "shipping_charge",
      "insurance_addon",
      "deposit",
      "penalty_fee",
      "membership"
    ],
    "by_code": {
      "goods_stock_retail": {
        "template_code": "goods_stock_retail",
        "label": "Goods (Stock)",
        "description": "Stock-tracked goods with optional variants.",
        "defaults": {"kind": "goods_stock", "cost_model": "inventory_layer", "attribute_set_code": "goods_stock", "capabilities": ["inventory_tracked","variantable"]},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Attributes"},{"code":"variants","label":"Variants"},{"code":"pricing","label":"Pricing"},{"code":"inventory","label":"Inventory"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","default_uom_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "variants": {"enabled": true, "variant_axes": ["color","size"], "sku_generation": {"pattern": "{code}-{color}-{size}"}},
            "pricing": {"fields": ["price_list_id","base_price","price_uom_id"]},
            "inventory": {"fields": ["default_location_id","reorder_point","reorder_qty"],"toggles": ["allow_negative_stock"]}
          }
        },
        "post_create": [{"action":"ensure_price_list_item"}]
      },
      "goods_nonstock_basic": {
        "template_code": "goods_nonstock_basic",
        "label": "Goods (Non-Stock / Drop-Ship)",
        "description": "Goods sold without inventory tracking.",
        "defaults": {"kind": "goods_nonstock", "cost_model": "direct_expense_per_sale", "attribute_set_code": "goods_nonstock", "capabilities": []},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Attributes"},{"code":"pricing","label":"Pricing"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "pricing": {"fields": ["price_list_id","base_price","price_uom_id"]}
          }
        },
        "post_create": [{"action":"ensure_price_list_item"}]
      },
      "consumable_basic": {
        "template_code": "consumable_basic",
        "label": "Consumable",
        "description": "Low-value consumables (optionally stock-tracked).",
        "defaults": {"kind": "consumable", "cost_model": "inventory_layer", "attribute_set_code": "consumable", "capabilities": ["inventory_tracked"]},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Details"},{"code":"pricing","label":"Pricing"},{"code":"inventory","label":"Inventory"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","default_uom_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "pricing": {"fields": ["price_list_id","base_price","price_uom_id"]},
            "inventory": {"fields": ["default_location_id"],"toggles": ["allow_negative_stock"]}
          }
        },
        "post_create": [{"action":"ensure_price_list_item"}]
      },
      "digital_good_basic": {
        "template_code": "digital_good_basic",
        "label": "Digital Good",
        "description": "Digitally delivered goods (no inventory).",
        "defaults": {"kind": "digital_good", "cost_model": "direct_expense_per_sale", "attribute_set_code": "digital_good", "capabilities": []},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Delivery"},{"code":"pricing","label":"Pricing"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "pricing": {"fields": ["price_list_id","base_price","price_uom_id"]}
          }
        },
        "post_create": [{"action":"ensure_price_list_item"}]
      },
      "bundle_package": {
        "template_code": "bundle_package",
        "label": "Bundle (Simple)",
        "description": "Logical bundle sold as one line; components optional for reference.",
        "defaults": {"kind": "bundle", "cost_model": "hybrid", "attribute_set_code": "bundle", "capabilities": ["package"]},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Details"},{"code":"components","label":"Components"},{"code":"pricing","label":"Pricing"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "components": {"enabled": true, "fields": ["component_items"], "help": "Optional: define components for costing/fulfillment."},
            "pricing": {"fields": ["price_list_id","base_price","price_uom_id"]}
          }
        },
        "post_create": [{"action":"ensure_price_list_item"}]
      },
      "gift_card": {
        "template_code": "gift_card",
        "label": "Gift Card / Voucher",
        "description": "Stored value product (deferred revenue).",
        "defaults": {"kind": "gift_card", "cost_model": "none", "attribute_set_code": "gift_card", "capabilities": []},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Voucher Rules"},{"code":"pricing","label":"Value"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "pricing": {"fields": ["base_price"], "help": "Base price represents voucher value."}
          }
        },
        "post_create": []
      },

      "service_professional": {
        "template_code": "service_professional",
        "label": "Service (Professional)",
        "description": "Project-based services with job costing.",
        "defaults": {"kind": "service_professional", "cost_model": "job_costing", "attribute_set_code": "service_professional", "capabilities": []},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Service Details"},{"code":"pricing","label":"Pricing"},{"code":"costing","label":"Costing"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "pricing": {"fields": ["price_list_id","base_price","price_uom_id"]},
            "costing": {"fields": ["default_job_template_id"], "help": "Optional: auto-create job/project on sales."}
          }
        },
        "post_create": [{"action":"ensure_price_list_item"}]
      },
      "service_managed": {
        "template_code": "service_managed",
        "label": "Service (Managed)",
        "description": "Ongoing service delivery (non-metered SaaS).",
        "defaults": {"kind": "service_managed", "cost_model": "hybrid", "attribute_set_code": "service_managed", "capabilities": []},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Service Scope"},{"code":"pricing","label":"Pricing"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "pricing": {"fields": ["price_list_id","base_price","price_uom_id"]}
          }
        },
        "post_create": [{"action":"ensure_price_list_item"}]
      },
      "service_labor": {
        "template_code": "service_labor",
        "label": "Service (Labor)",
        "description": "Time-based labor services.",
        "defaults": {"kind": "service_labor", "cost_model": "job_costing", "attribute_set_code": "service_labor", "capabilities": []},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Time Rules"},{"code":"pricing","label":"Rates"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "pricing": {"fields": ["price_list_id","base_price","price_uom_id"], "help": "Price UOM typically hour/day."}
          }
        },
        "post_create": [{"action":"ensure_price_list_item"}]
      },
      "service_fee": {
        "template_code": "service_fee",
        "label": "Service (Fee)",
        "description": "Administrative/transaction fees.",
        "defaults": {"kind": "service_fee", "cost_model": "direct_expense_per_sale", "attribute_set_code": "service_fee", "capabilities": []},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Fee Rules"},{"code":"pricing","label":"Pricing"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "pricing": {"fields": ["base_price","price_uom_id"], "help": "Often per invoice or per transaction."}
          }
        },
        "post_create": []
      },
      "service_installation": {
        "template_code": "service_installation",
        "label": "Service (Installation)",
        "description": "Installation/setup tied to goods.",
        "defaults": {"kind": "service_installation", "cost_model": "job_costing", "attribute_set_code": "service_installation", "capabilities": []},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Scope"},{"code":"pricing","label":"Pricing"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "pricing": {"fields": ["base_price","price_uom_id"]}
          }
        },
        "post_create": [{"action":"ensure_price_list_item"}]
      },

      "accommodation_room_type": {
        "template_code": "accommodation_room_type",
        "label": "Accommodation",
        "description": "Bookable lodging sold by stay/night.",
        "defaults": {"kind": "accommodation", "cost_model": "hybrid", "attribute_set_code": "accommodation", "capabilities": ["bookable"]},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Room Details"},{"code":"pool","label":"Pool & Capacity"},{"code":"instances","label":"Rooms"},{"code":"pricing","label":"Pricing"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "pool": {"fields": ["branch_id","default_capacity"], "help": "Creates a resource_pool."},
            "instances": {"enabled": true, "fields": ["room_number","floor","notes"], "mode": "bulk_or_single"},
            "pricing": {"fields": ["price_list_id","base_price","price_uom_id"], "help": "Nightly rate typically uses UOM=night."}
          }
        },
        "post_create": [{"action":"create_resource_pool"},{"action":"ensure_price_list_item"}]
      },
      "venue_booking": {
        "template_code": "venue_booking",
        "label": "Venue Booking",
        "description": "Bookable space rental by time.",
        "defaults": {"kind": "venue_booking", "cost_model": "hybrid", "attribute_set_code": "venue_booking", "capabilities": ["bookable"]},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Venue Details"},{"code":"pool","label":"Pool"},{"code":"instances","label":"Spaces"},{"code":"pricing","label":"Pricing"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "pool": {"fields": ["branch_id","default_capacity"]},
            "instances": {"enabled": true, "fields": ["space_code","notes"], "mode": "bulk_or_single"},
            "pricing": {"fields": ["price_list_id","base_price","price_uom_id"], "help": "Often UOM=hour."}
          }
        },
        "post_create": [{"action":"create_resource_pool"},{"action":"ensure_price_list_item"}]
      },
      "event_ticket_resale": {
        "template_code": "event_ticket_resale",
        "label": "Event Ticket",
        "description": "Ticket sales with optional dated occurrences.",
        "defaults": {"kind": "event_ticket", "cost_model": "direct_expense_per_sale", "attribute_set_code": "event_ticket", "capabilities": ["bookable"]},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Event Details"},{"code":"capacity","label":"Capacity"},{"code":"pricing","label":"Pricing"},{"code":"costing","label":"Cost"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "capacity": {"fields": ["uses_occurrence","default_capacity"], "help": "Enable occurrences for dated events."},
            "pricing": {"fields": ["price_list_id","base_price","price_uom_id"]},
            "costing": {"fields": ["default_unit_cost"], "help": "Per-ticket organizer cost (optional default)."}
          }
        },
        "post_create": [{"action":"ensure_price_list_item"}]
      },
      "tour_activity": {
        "template_code": "tour_activity",
        "label": "Tour / Activity",
        "description": "Scheduled departures; may bundle components.",
        "defaults": {"kind": "tour_activity", "cost_model": "hybrid", "attribute_set_code": "tour_activity", "capabilities": ["bookable"]},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Activity Details"},{"code":"occurrence","label":"Departures"},{"code":"pricing","label":"Pricing"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "occurrence": {"enabled": true, "fields": ["uses_occurrence","default_capacity"], "help": "Define departure schedules as occurrences."},
            "pricing": {"fields": ["price_list_id","base_price","price_uom_id"], "help": "Often per person."}
          }
        },
        "post_create": [{"action":"ensure_price_list_item"}]
      },
      "appointment": {
        "template_code": "appointment",
        "label": "Appointment",
        "description": "1:1 or limited-slot appointment scheduling.",
        "defaults": {"kind": "appointment", "cost_model": "job_costing", "attribute_set_code": "appointment", "capabilities": ["bookable"]},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Appointment Rules"},{"code":"pool","label":"Resources"},{"code":"pricing","label":"Pricing"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "pool": {"fields": ["branch_id","default_capacity"], "help": "Resource pool could represent staff/calendar resources."},
            "pricing": {"fields": ["base_price","price_uom_id"], "help": "Often per session."}
          }
        },
        "post_create": [{"action":"create_resource_pool"},{"action":"ensure_price_list_item"}]
      },

      "asset_rental_class": {
        "template_code": "asset_rental_class",
        "label": "Asset Rental",
        "description": "Rental backed by physical assets; instance assignment required.",
        "defaults": {"kind": "asset_rental", "cost_model": "asset_usage_costing", "attribute_set_code": "asset_rental", "capabilities": ["bookable","rental","serialized"]},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Class Details"},{"code":"policy","label":"Rental Policy"},{"code":"pool","label":"Pool"},{"code":"instances","label":"Assets"},{"code":"pricing","label":"Pricing"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "policy": {"fields": ["billing_granularity","min_duration_minutes","deposit_amount","fuel_policy","mileage_included","mileage_uom_id"]},
            "pool": {"fields": ["branch_id"]},
            "instances": {"enabled": true, "fields": ["asset_id","identifier","status"], "mode": "bulk_or_single"},
            "pricing": {"fields": ["price_list_id","base_price","price_uom_id"], "help": "Often UOM=hour/day/week."}
          }
        },
        "post_create": [{"action":"create_resource_pool"},{"action":"create_rental_policy"},{"action":"ensure_price_list_item"}]
      },
      "rental_with_operator": {
        "template_code": "rental_with_operator",
        "label": "Rental with Operator",
        "description": "Rental + labor/operator bundled.",
        "defaults": {"kind": "rental_with_operator", "cost_model": "hybrid", "attribute_set_code": "rental_with_operator", "capabilities": ["bookable","rental","serialized"]},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Details"},{"code":"policy","label":"Policy"},{"code":"pool","label":"Pool"},{"code":"instances","label":"Assets"},{"code":"pricing","label":"Pricing"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "policy": {"fields": ["billing_granularity","deposit_amount"]},
            "pool": {"fields": ["branch_id"]},
            "instances": {"enabled": true, "fields": ["asset_id","identifier","status"], "mode": "bulk_or_single"},
            "pricing": {"fields": ["base_price","price_uom_id"], "help": "May include driver fee."}
          }
        },
        "post_create": [{"action":"create_resource_pool"},{"action":"create_rental_policy"},{"action":"ensure_price_list_item"}]
      },
      "lease": {
        "template_code": "lease",
        "label": "Lease",
        "description": "Long-term leasing sold as a service/rental product.",
        "defaults": {"kind": "lease", "cost_model": "asset_usage_costing", "attribute_set_code": "lease", "capabilities": ["rental","serialized"]},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Lease Terms"},{"code":"policy","label":"Billing"},{"code":"pricing","label":"Pricing"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "policy": {"fields": ["billing_period","requires_schedule"], "help": "Lease billing may require schedules."},
            "pricing": {"fields": ["base_price","price_uom_id"], "help": "Often UOM=month."}
          }
        },
        "post_create": [{"action":"ensure_price_list_item"}]
      },

      "air_ticket_resale": {
        "template_code": "air_ticket_resale",
        "label": "Air Ticket (Agency)",
        "description": "Airline ticket sales; costs may use prepaid deposit/top-up consumption.",
        "defaults": {"kind": "air_ticket_resale", "cost_model": "prepaid_consumption", "attribute_set_code": "air_ticket", "capabilities": []},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Ticket Details"},{"code":"pricing","label":"Pricing"},{"code":"prepaid","label":"Prepaid/Deposit"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "pricing": {"fields": ["price_list_id","base_price","price_uom_id"]},
            "prepaid": {"fields": ["prepaid_account_id","airline_partner_id"], "help": "Optional: prepaid funding source."}
          }
        },
        "post_create": [{"action":"ensure_price_list_item"}]
      },
      "train_ticket_resale": {
        "template_code": "train_ticket_resale",
        "label": "Train Ticket (Agency)",
        "description": "Train ticket resale with per-ticket cost.",
        "defaults": {"kind": "train_ticket_resale", "cost_model": "direct_expense_per_sale", "attribute_set_code": "train_ticket", "capabilities": []},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Ticket Details"},{"code":"pricing","label":"Pricing"},{"code":"costing","label":"Cost"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "pricing": {"fields": ["base_price","price_uom_id"]},
            "costing": {"fields": ["default_unit_cost"]}
          }
        },
        "post_create": [{"action":"ensure_price_list_item"}]
      },
      "bus_ferry_ticket_resale": {
        "template_code": "bus_ferry_ticket_resale",
        "label": "Bus/Ferry Ticket (Agency)",
        "description": "Bus or ferry ticket resale with per-ticket cost.",
        "defaults": {"kind": "bus_ferry_ticket_resale", "cost_model": "direct_expense_per_sale", "attribute_set_code": "bus_ferry_ticket", "capabilities": []},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Ticket Details"},{"code":"pricing","label":"Pricing"},{"code":"costing","label":"Cost"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "pricing": {"fields": ["base_price","price_uom_id"]},
            "costing": {"fields": ["default_unit_cost"]}
          }
        },
        "post_create": [{"action":"ensure_price_list_item"}]
      },
      "hotel_resale": {
        "template_code": "hotel_resale",
        "label": "Hotel (Agency)",
        "description": "Hotel resale/agency model with per-booking cost.",
        "defaults": {"kind": "hotel_resale", "cost_model": "direct_expense_per_sale", "attribute_set_code": "hotel_resale", "capabilities": ["bookable"]},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Hotel Details"},{"code":"pricing","label":"Pricing"},{"code":"costing","label":"Cost"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "pricing": {"fields": ["base_price","price_uom_id"]},
            "costing": {"fields": ["default_unit_cost"]}
          }
        },
        "post_create": [{"action":"ensure_price_list_item"}]
      },
      "travel_package": {
        "template_code": "travel_package",
        "label": "Travel Package",
        "description": "Bundled travel offering (hotel + tickets + tours).",
        "defaults": {"kind": "travel_package", "cost_model": "hybrid", "attribute_set_code": "travel_package", "capabilities": ["package"]},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Package Details"},{"code":"components","label":"Components"},{"code":"pricing","label":"Pricing"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "components": {"enabled": true, "fields": ["component_items"], "help": "Define components for costing/fulfillment."},
            "pricing": {"fields": ["base_price","price_uom_id"]}
          }
        },
        "post_create": [{"action":"ensure_price_list_item"}]
      },

      "shipping_charge": {
        "template_code": "shipping_charge",
        "label": "Shipping Charge",
        "description": "Delivery/logistics fee charged to customers.",
        "defaults": {"kind": "shipping_charge", "cost_model": "direct_expense_per_sale", "attribute_set_code": "shipping_charge", "capabilities": []},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Rules"},{"code":"pricing","label":"Pricing"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "pricing": {"fields": ["base_price","price_uom_id"], "help": "Often per shipment."}
          }
        },
        "post_create": []
      },
      "insurance_addon": {
        "template_code": "insurance_addon",
        "label": "Insurance Add-on",
        "description": "Optional insurance sold alongside products.",
        "defaults": {"kind": "insurance_addon", "cost_model": "direct_expense_per_sale", "attribute_set_code": "insurance_addon", "capabilities": []},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Coverage"},{"code":"pricing","label":"Pricing"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "pricing": {"fields": ["base_price","price_uom_id"]}
          }
        },
        "post_create": []
      },
      "deposit": {
        "template_code": "deposit",
        "label": "Deposit (Refundable)",
        "description": "Refundable security deposit product (liability).",
        "defaults": {"kind": "deposit", "cost_model": "none", "attribute_set_code": "deposit", "capabilities": []},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Deposit Rules"},{"code":"pricing","label":"Amount"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "pricing": {"fields": ["base_price"], "help": "Deposit amount."}
          }
        },
        "post_create": []
      },
      "penalty_fee": {
        "template_code": "penalty_fee",
        "label": "Penalty Fee",
        "description": "Penalty/late/cancellation fees.",
        "defaults": {"kind": "penalty_fee", "cost_model": "none", "attribute_set_code": "penalty_fee", "capabilities": []},
        "ui": {
          "wizard": {"enabled": false, "steps": []},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "pricing": {"fields": ["base_price","price_uom_id"]}
          }
        },
        "post_create": []
      },
      "membership": {
        "template_code": "membership",
        "label": "Membership",
        "description": "Entitlement/access product (non-stored-value).",
        "defaults": {"kind": "membership", "cost_model": "hybrid", "attribute_set_code": "membership", "capabilities": []},
        "ui": {
          "wizard": {"enabled": true, "steps": [{"code":"base","label":"Basic Info"},{"code":"attributes","label":"Entitlements"},{"code":"pricing","label":"Pricing"}]},
          "sections": {
            "base": {"fields": ["code","name","category_id","tax_category_id","active"]},
            "attributes": {"source":"attribute_set","mode":"dynamic"},
            "pricing": {"fields": ["base_price","price_uom_id"], "help": "Often per month/year."}
          }
        },
        "post_create": [{"action":"ensure_price_list_item"}]
      }
    }
  },

  "validation": {
    "base_schema": {
      "required": ["code", "name", "kind"],
      "optional": [
        "description",
        "category_id",
        "tax_category_id",
        "active",
        "default_uom_id",
        "cost_model",
        "attribute_set_code",
        "attrs_json",
        "prepaid_account_id",
        "airline_partner_id"
      ],
      "forbidden": ["qty_on_hand", "unit_cost", "avg_cost", "stock_value"]
    },
    "capability_rules": {
      "inventory_tracked": {"requires_fields": ["default_uom_id"], "forbids_capabilities": ["bookable"], "allows": {"inventory": true}},
      "variantable": {"allows": {"variants": true}},
      "bookable": {"forbids_capabilities": ["inventory_tracked"], "allows": {"resource_pool": true}},
      "rental": {"requires_capabilities": ["serialized"], "allows": {"rental_policy": true}},
      "serialized": {"allows": {"resource_instances": true}},
      "package": {"allows": {"components": true}}
    },
    "cost_model_rules": {
      "none": {"notes": "No COGS tracking."},
      "inventory_layer": {"requires_capabilities": ["inventory_tracked"]},
      "direct_expense_per_sale": {"forbids_capabilities": ["inventory_tracked"], "allows": {"invoice_detail_cost": true}},
      "job_costing": {"forbids_capabilities": ["inventory_tracked"], "allows": {"jobs": true}},
      "asset_usage_costing": {"requires_any_capabilities": ["rental", "serialized"], "forbids_capabilities": ["inventory_tracked"], "allows": {"asset_cost_pools": true}},
      "prepaid_consumption": {"forbids_capabilities": ["inventory_tracked"], "requires_fields": ["prepaid_account_id"], "allows": {"prepaid": true}},
      "hybrid": {"allows": {"invoice_detail_cost": true, "allocations": true}}
    },
    "kind_rules": [
      {"kind": "goods_stock", "requires_capabilities": ["inventory_tracked","variantable"], "forbids_capabilities": ["bookable","rental"], "requires_fields": ["default_uom_id"], "requires_attribute_codes": [], "allowed_cost_models": ["inventory_layer"], "ui": {"template_codes": ["goods_stock_retail"]}},
      {"kind": "goods_nonstock", "requires_capabilities": [], "forbids_capabilities": ["inventory_tracked","bookable","rental"], "requires_fields": [], "requires_attribute_codes": [], "allowed_cost_models": ["direct_expense_per_sale","hybrid"], "ui": {"template_codes": ["goods_nonstock_basic"]}},
      {"kind": "consumable", "requires_capabilities": [], "forbids_capabilities": ["bookable","rental"], "requires_fields": ["default_uom_id"], "requires_attribute_codes": [], "allowed_cost_models": ["inventory_layer","direct_expense_per_sale"], "ui": {"template_codes": ["consumable_basic"]}},
      {"kind": "digital_good", "requires_capabilities": [], "forbids_capabilities": ["inventory_tracked","bookable","rental"], "requires_fields": [], "requires_attribute_codes": ["delivery_method"], "allowed_cost_models": ["direct_expense_per_sale","hybrid"], "ui": {"template_codes": ["digital_good_basic"]}},
      {"kind": "bundle", "requires_capabilities": ["package"], "forbids_capabilities": ["inventory_tracked","bookable","rental"], "requires_fields": [], "requires_attribute_codes": [], "allowed_cost_models": ["hybrid"], "ui": {"template_codes": ["bundle_package"]}},
      {"kind": "gift_card", "requires_capabilities": [], "forbids_capabilities": ["inventory_tracked","bookable","rental","package"], "requires_fields": [], "requires_attribute_codes": ["expiry_days"], "allowed_cost_models": ["none"], "ui": {"template_codes": ["gift_card"]}},

      {"kind": "service_professional", "requires_capabilities": [], "forbids_capabilities": ["inventory_tracked","bookable","rental"], "requires_fields": [], "requires_attribute_codes": [], "allowed_cost_models": ["job_costing","hybrid"], "ui": {"template_codes": ["service_professional"]}},
      {"kind": "service_managed", "requires_capabilities": [], "forbids_capabilities": ["inventory_tracked","bookable","rental"], "requires_fields": [], "requires_attribute_codes": ["sla_response_hours"], "allowed_cost_models": ["hybrid","job_costing"], "ui": {"template_codes": ["service_managed"]}},
      {"kind": "service_labor", "requires_capabilities": [], "forbids_capabilities": ["inventory_tracked","rental"], "requires_fields": [], "requires_attribute_codes": ["minimum_hours"], "allowed_cost_models": ["job_costing","hybrid"], "ui": {"template_codes": ["service_labor"]}},
      {"kind": "service_fee", "requires_capabilities": [], "forbids_capabilities": ["inventory_tracked","bookable","rental"], "requires_fields": [], "requires_attribute_codes": ["fee_basis"], "allowed_cost_models": ["direct_expense_per_sale","none"], "ui": {"template_codes": ["service_fee"]}},
      {"kind": "service_installation", "requires_capabilities": [], "forbids_capabilities": ["inventory_tracked","rental"], "requires_fields": [], "requires_attribute_codes": [], "allowed_cost_models": ["job_costing","hybrid"], "ui": {"template_codes": ["service_installation"]}},

      {"kind": "accommodation", "requires_capabilities": ["bookable"], "forbids_capabilities": ["inventory_tracked","variantable","rental"], "requires_fields": [], "requires_attribute_codes": ["max_occupancy"], "allowed_cost_models": ["hybrid"], "ui": {"template_codes": ["accommodation_room_type"]}},
      {"kind": "venue_booking", "requires_capabilities": ["bookable"], "forbids_capabilities": ["inventory_tracked","variantable","rental"], "requires_fields": [], "requires_attribute_codes": ["max_capacity"], "allowed_cost_models": ["hybrid"], "ui": {"template_codes": ["venue_booking"]}},
      {"kind": "event_ticket", "requires_capabilities": ["bookable"], "forbids_capabilities": ["inventory_tracked","variantable","rental"], "requires_fields": [], "requires_attribute_codes": ["venue_name"], "allowed_cost_models": ["direct_expense_per_sale","hybrid"], "ui": {"template_codes": ["event_ticket_resale"]}},
      {"kind": "tour_activity", "requires_capabilities": ["bookable"], "forbids_capabilities": ["inventory_tracked","variantable","rental"], "requires_fields": [], "requires_attribute_codes": ["meeting_point"], "allowed_cost_models": ["hybrid"], "ui": {"template_codes": ["tour_activity"]}},
      {"kind": "appointment", "requires_capabilities": ["bookable"], "forbids_capabilities": ["inventory_tracked","variantable","rental"], "requires_fields": [], "requires_attribute_codes": ["duration_minutes"], "allowed_cost_models": ["job_costing","hybrid"], "ui": {"template_codes": ["appointment"]}},

      {"kind": "asset_rental", "requires_capabilities": ["bookable","rental","serialized"], "forbids_capabilities": ["inventory_tracked","variantable"], "requires_fields": [], "requires_attribute_codes": [], "allowed_cost_models": ["asset_usage_costing","hybrid"], "ui": {"template_codes": ["asset_rental_class"]}},
      {"kind": "rental_with_operator", "requires_capabilities": ["bookable","rental","serialized"], "forbids_capabilities": ["inventory_tracked","variantable"], "requires_fields": [], "requires_attribute_codes": [], "allowed_cost_models": ["hybrid","asset_usage_costing"], "ui": {"template_codes": ["rental_with_operator"]}},
      {"kind": "lease", "requires_capabilities": ["rental","serialized"], "forbids_capabilities": ["inventory_tracked","variantable"], "requires_fields": [], "requires_attribute_codes": ["tenor_months"], "allowed_cost_models": ["asset_usage_costing","hybrid"], "ui": {"template_codes": ["lease"]}},

      {"kind": "air_ticket_resale", "requires_capabilities": [], "forbids_capabilities": ["inventory_tracked","rental"], "requires_fields": ["prepaid_account_id"], "requires_attribute_codes": ["airline"], "allowed_cost_models": ["prepaid_consumption","direct_expense_per_sale"], "ui": {"template_codes": ["air_ticket_resale"]}},
      {"kind": "train_ticket_resale", "requires_capabilities": [], "forbids_capabilities": ["inventory_tracked","rental"], "requires_fields": [], "requires_attribute_codes": ["operator"], "allowed_cost_models": ["direct_expense_per_sale","hybrid"], "ui": {"template_codes": ["train_ticket_resale"]}},
      {"kind": "bus_ferry_ticket_resale", "requires_capabilities": [], "forbids_capabilities": ["inventory_tracked","rental"], "requires_fields": [], "requires_attribute_codes": ["operator"], "allowed_cost_models": ["direct_expense_per_sale","hybrid"], "ui": {"template_codes": ["bus_ferry_ticket_resale"]}},
      {"kind": "hotel_resale", "requires_capabilities": ["bookable"], "forbids_capabilities": ["inventory_tracked","rental"], "requires_fields": [], "requires_attribute_codes": ["property_name"], "allowed_cost_models": ["direct_expense_per_sale","hybrid"], "ui": {"template_codes": ["hotel_resale"]}},
      {"kind": "travel_package", "requires_capabilities": ["package"], "forbids_capabilities": ["inventory_tracked","rental"], "requires_fields": [], "requires_attribute_codes": ["itinerary"], "allowed_cost_models": ["hybrid"], "ui": {"template_codes": ["travel_package"]}},

      {"kind": "shipping_charge", "requires_capabilities": [], "forbids_capabilities": ["inventory_tracked","bookable","rental"], "requires_fields": [], "requires_attribute_codes": [], "allowed_cost_models": ["direct_expense_per_sale","none"], "ui": {"template_codes": ["shipping_charge"]}},
      {"kind": "insurance_addon", "requires_capabilities": [], "forbids_capabilities": ["inventory_tracked","bookable","rental"], "requires_fields": [], "requires_attribute_codes": ["insurer"], "allowed_cost_models": ["direct_expense_per_sale","hybrid"], "ui": {"template_codes": ["insurance_addon"]}},
      {"kind": "deposit", "requires_capabilities": [], "forbids_capabilities": ["inventory_tracked","bookable","rental","package"], "requires_fields": [], "requires_attribute_codes": ["refundable"], "allowed_cost_models": ["none"], "ui": {"template_codes": ["deposit"]}},
      {"kind": "penalty_fee", "requires_capabilities": [], "forbids_capabilities": ["inventory_tracked","bookable","rental","package"], "requires_fields": [], "requires_attribute_codes": ["trigger"], "allowed_cost_models": ["none"], "ui": {"template_codes": ["penalty_fee"]}},
      {"kind": "membership", "requires_capabilities": [], "forbids_capabilities": ["inventory_tracked","rental"], "requires_fields": [], "requires_attribute_codes": ["duration_unit","duration_value"], "allowed_cost_models": ["hybrid","job_costing","none"], "ui": {"template_codes": ["membership"]}}
    ],
    "execution_order": [
      "validate_base_schema",
      "validate_kind_rules",
      "validate_capabilities_against_kind",
      "validate_cost_model_rules",
      "validate_required_attribute_codes"
    ]
  }
}
```

