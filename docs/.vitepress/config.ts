import { defineConfig } from 'vitepress';

const repo = 'https://github.com/obsidianlabs-io/obsidian-admin-laravel';
const frontendRepo = 'https://github.com/obsidianlabs-io/obsidian-admin-vue';

export default defineConfig({
  base: process.env.VITEPRESS_BASE || '/',
  title: 'Obsidian Admin Laravel',
  description: 'Strictly-typed Laravel 12 admin backend for enterprise systems, SaaS platforms, and contract-driven frontend pairing.',
  lang: 'en-US',
  cleanUrls: true,
  lastUpdated: true,
  metaChunk: true,
  head: [['link', { rel: 'icon', href: '/favicon.svg' }]],
  themeConfig: {
    logo: '/favicon.svg',
    nav: [
      { text: 'Guide', link: '/getting-started' },
      { text: 'Architecture', link: '/architecture' },
      { text: 'Runtime', link: '/octane' },
      { text: 'GitHub', link: repo }
    ],
    sidebar: [
      {
        text: 'Getting Started',
        items: [
          { text: 'Overview', link: '/' },
          { text: 'Getting Started', link: '/getting-started' },
          { text: 'Compatibility Matrix', link: '/compatibility-matrix' },
          { text: 'Production Runtime', link: '/production-runtime' },
          { text: 'Project Profiles', link: '/project-profiles' },
          { text: 'Security Checklist', link: '/security-checklist' }
        ]
      },
      {
        text: 'Architecture & Operations',
        items: [
          { text: 'Backend Architecture', link: '/architecture' },
          { text: 'Octane Runtime', link: '/octane' },
          { text: 'Operations Hardening', link: '/operations-hardening' },
          { text: 'Deletion Governance', link: '/deletion-governance' }
        ]
      },
      {
        text: 'Contracts & Releases',
        items: [
          { text: 'OpenAPI Contract', link: '/openapi.yaml' },
          { text: 'API Contract Snapshot', link: '/api-contract.snapshot' },
          { text: 'API Error Catalog', link: '/api-error-catalog' },
          { text: 'Release SOP', link: '/release-sop' },
          { text: 'Release Final Checklist', link: '/release-final-checklist' }
        ]
      },
      {
        text: 'GitHub Project Ops',
        items: [
          { text: 'Repository Setup Checklist', link: '/github/repository-setup-checklist' },
          { text: 'Repository Metadata', link: '/github/repository-metadata' }
        ]
      }
    ],
    socialLinks: [{ icon: 'github', link: repo }],
    editLink: {
      pattern: `${repo}/edit/main/docs/:path`,
      text: 'Edit this page on GitHub'
    },
    footer: {
      message: `Pair with <a href="${frontendRepo}" target="_blank" rel="noreferrer">Obsidian Admin Vue</a> for the full contract-driven stack.`,
      copyright: 'Released under the MIT License.'
    },
    search: {
      provider: 'local'
    },
    outline: {
      level: [2, 3]
    },
    docFooter: {
      prev: 'Previous page',
      next: 'Next page'
    }
  }
});
