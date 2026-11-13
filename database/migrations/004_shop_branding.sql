-- 004: online shop logo, browser-tab icon (favicon) and "show name next to logo"
ALTER TABLE shop_settings
  ADD COLUMN logo VARCHAR(200) NULL,
  ADD COLUMN icon VARCHAR(200) NULL COMMENT '192 px square PNG',
  ADD COLUMN icon_small VARCHAR(200) NULL COMMENT '32 px square PNG (browser tab)',
  ADD COLUMN icon_custom TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = uploaded separately, 0 = made from the logo',
  ADD COLUMN show_name TINYINT(1) NOT NULL DEFAULT 1
