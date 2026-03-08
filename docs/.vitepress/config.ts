import process from 'node:process';
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
          { text: 'Full-Stack Evaluation', link: '/full-stack-evaluation' },
          { text: 'Compatibility Matrix', link: '/compatibility-matrix' },
          { text: 'Multi-Tenancy', link: '/multi-tenancy' },
          { text: 'Tenant Switching Semantics', link: '/tenant-switching-semantics' },
          { text: 'Testing', link: '/testing' },
          { text: 'Health Model', link: '/health-model' },
          { text: 'Runtime Topology', link: '/runtime-topology' },
          { text: 'Production Runtime', link: '/production-runtime' },
          { text: 'Project Profiles', link: '/project-profiles' },
          { text: 'Security Checklist', link: '/security-checklist' },
          { text: 'Security Baseline', link: '/security-baseline' },
          { text: 'Open Source Launch Checklist', link: '/open-source-launch-checklist' },
          { text: 'Full-Stack Demo Environment', link: '/full-stack-demo-environment' },
          { text: 'Demo Deployment Runbook', link: '/demo-deployment-runbook' },
          { text: 'Evaluator Demo Validation', link: '/evaluator-demo-validation' }
        ]
      },
      {
        text: 'Architecture & Operations',
        items: [
          { text: 'Backend Architecture', link: '/architecture' },
          { text: 'RBAC and Role Levels', link: '/rbac-and-role-levels' },
          { text: 'Session and 2FA', link: '/session-and-2fa' },
          { text: 'Audit and Compliance', link: '/audit-and-compliance' },
          { text: 'Feature Flags', link: '/feature-flags' },
          { text: 'Realtime', link: '/realtime' },
          { text: 'Octane Runtime', link: '/octane' },
          { text: 'Operations Hardening', link: '/operations-hardening' },
          { text: 'Deletion Lifecycle', link: '/deletion-lifecycle' },
          { text: 'Deletion Governance', link: '/deletion-governance' }
        ]
      },
      {
        text: 'Contracts & Releases',
        items: [
          { text: 'OpenAPI Contract', link: '/openapi.yaml' },
          { text: 'OpenAPI Workflow', link: '/openapi-workflow' },
          { text: 'API Contract Snapshot', link: '/api-contract.snapshot' },
          { text: 'API Error Catalog', link: '/api-error-catalog' },
          { text: 'Release Artifacts', link: '/release-artifacts' },
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
