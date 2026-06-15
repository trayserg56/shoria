import type { GlobalThemeOverrides } from 'naive-ui'

/** shadcn-подобная монохромная палитра (zinc / чёрный primary) */
export const naiveThemeOverrides: GlobalThemeOverrides = {
  common: {
    primaryColor: '#09090b',
    primaryColorHover: '#18181b',
    primaryColorPressed: '#000000',
    primaryColorSuppl: '#27272a',
    bodyColor: '#ffffff',
    textColorBase: '#111827',
    textColor1: '#171717',
    textColor2: '#52525b',
    textColor3: '#a1a1aa',
    borderColor: '#e5e7eb',
    dividerColor: '#e5e7eb',
    hoverColor: '#f4f4f5',
    placeholderColor: '#9ca3af',
    closeIconColor: '#6b7280',
    closeIconColorHover: '#111827',
    closeIconColorPressed: '#111827',
    borderRadius: '8px',
    borderRadiusSmall: '6px',
    fontFamily: 'var(--font-body)',
    fontSize: '16px',
    fontSizeMedium: '16px',
    heightMedium: '44px',
    heightLarge: '48px',
    heightSmall: '36px',
  },
  Button: {
    borderRadiusTiny: '6px',
    borderRadiusSmall: '8px',
    borderRadiusMedium: '8px',
    borderRadiusLarge: '10px',
    fontWeightStrong: '600',
  },
  Input: {
    borderRadius: '8px',
    heightMedium: '44px',
    heightLarge: '48px',
    heightSmall: '36px',
  },
  InternalSelection: {
    borderRadius: '8px',
    heightMedium: '44px',
  },
  Checkbox: {
    borderRadius: '4px',
    sizeMedium: '16px',
  },
  Progress: {
    railColor: '#e5e7eb',
    fillColor: '#09090b',
  },
  // Меню селекта / поповеры выше липкой шапки (≈50) и типичных модалок
  Popover: {
    zIndex: 5000,
  },
  InternalSelectMenu: {
    zIndex: 5000,
  },
}

export const naiveDarkThemeOverrides: GlobalThemeOverrides = {
  common: {
    primaryColor: '#e4e4e7',
    primaryColorHover: '#f4f4f5',
    primaryColorPressed: '#ffffff',
    primaryColorSuppl: '#d4d4d8',
    bodyColor: '#0f1117',
    baseColor: '#0f1117',
    textColorBase: '#f0f0f2',
    textColor1: '#f0f0f2',
    textColor2: '#c4c4ce',
    textColor3: '#8b8fa8',
    borderColor: '#2a2e42',
    dividerColor: '#2a2e42',
    hoverColor: '#22263a',
    popoverColor: '#1a1d27',
    tableColor: '#1a1d27',
    cardColor: '#1a1d27',
    modalColor: '#1a1d27',
    placeholderColor: '#8b8fa8',
    closeIconColor: '#8b8fa8',
    closeIconColorHover: '#f0f0f2',
    closeIconColorPressed: '#f0f0f2',
    borderRadius: '8px',
    borderRadiusSmall: '6px',
    fontFamily: 'var(--font-body)',
    fontSize: '16px',
    fontSizeMedium: '16px',
    heightMedium: '44px',
    heightLarge: '48px',
    heightSmall: '36px',
  },
  Button: {
    borderRadiusTiny: '6px',
    borderRadiusSmall: '8px',
    borderRadiusMedium: '8px',
    borderRadiusLarge: '10px',
    fontWeightStrong: '600',
  },
  Input: {
    borderRadius: '8px',
    heightMedium: '44px',
    heightLarge: '48px',
    heightSmall: '36px',
  },
  InternalSelection: {
    borderRadius: '8px',
    heightMedium: '44px',
  },
  Checkbox: {
    borderRadius: '4px',
    sizeMedium: '16px',
  },
  Progress: {
    railColor: '#2a2e42',
    fillColor: '#e4e4e7',
  },
  Popover: {
    zIndex: 5000,
  },
  InternalSelectMenu: {
    zIndex: 5000,
  },
}
