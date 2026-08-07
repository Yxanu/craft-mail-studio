export type BlockType = 'heading' | 'richText' | 'button' | 'image' | 'callout' | 'divider' | 'keyValue'

export interface EmailBlock {
  id: string
  type: BlockType
  data: Record<string, any>
}

export interface TemplateDocument {
  version: 1
  blocks: EmailBlock[]
}

export interface Revision {
  id: number
  number: number
  createdAt: string
  createdBy: number | null
  active: boolean
}

export interface TemplateVariant {
  id: number
  siteId: number | null
  locale: string | null
  subject: string
  preheader: string
  document: TemplateDocument
  activeRevisionId: number | null
  revisions: Revision[]
}

export interface Mapping {
  id: number
  adapter: string
  sourceKey: string
  siteId: number | null
  locale: string | null
}

export interface EmailTemplate {
  id: number
  uid: string
  name: string
  handle: string
  description: string
  presetKey: string | null
  brandProfileId: number | null
  variant: TemplateVariant
  variants: TemplateVariant[]
  mappings: Mapping[]
  updatedAt: string
}

export interface SourceDefinition {
  key: string
  label: string
  adapter: string
  description: string | null
  variables: Record<string, { label?: string; type?: string; required?: boolean; example?: unknown }>
  sampleData: Record<string, unknown>
}

export interface Preset {
  key: string
  name: string
  description: string
  subject: string
  preheader: string
  document: TemplateDocument
}

export interface BrandProfile {
  id: number
  uid: string
  name: string
  handle: string
  isDefault: boolean
  config: Record<string, any>
}

export interface SiteDefinition {
  id: number
  name: string
  handle: string
  language: string
  primary: boolean
}

export interface BootstrapData {
  edition: 'free' | 'pro'
  isPro: boolean
  permissions: { edit: boolean; publish: boolean; test: boolean; manage: boolean }
  templates: EmailTemplate[]
  sources: SourceDefinition[]
  presets: Preset[]
  brands: BrandProfile[]
  sites: SiteDefinition[]
  defaultLocale: string
}
