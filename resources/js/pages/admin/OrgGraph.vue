<script setup lang="ts">
import { onMounted, ref, computed, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import axios from 'axios';

const page = usePage();
const graphData = Array.isArray(page.props.graph) ? page.props.graph : [];
const towns = Array.isArray(page.props.towns) ? page.props.towns : [];

// Define a type for branch objects (adjust fields as needed)
type Branch = { id: number; name: string; towns?: any[] };
const selectedBranch = ref<Branch | null>(null);
const selectedAgencyId = ref<number|null>(null);

import type { Network } from 'vis-network';
const orgNetworkRef = ref<Network | null>(null);
const townsNetworkRef = ref<Network | null>(null);

// Track expanded state - load from localStorage if available
const loadExpandedState = (key: string) => {
  try {
    const stored = localStorage.getItem(`orgGraph_${key}`);
    return stored ? new Set(JSON.parse(stored)) : new Set();
  } catch {
    return new Set();
  }
};

const saveExpandedState = (key: string, set: Set<any>) => {
  try {
    localStorage.setItem(`orgGraph_${key}`, JSON.stringify([...set]));
  } catch {}
};

const expandedAgencies = ref(loadExpandedState('agencies'));
const expandedDelegates = ref(loadExpandedState('delegates'));
const expandedBranches = ref(loadExpandedState('branches'));
const expandedDistricts = ref(loadExpandedState('districts'));

function selectAgency(id: number | null) {
  selectedAgencyId.value = id;
  selectedBranch.value = null;
}

function nodeId(type: string, id: number) {
  return `${type}-${id}`;
}

function getDistrictId(town: any): number | null {
  if (!town) {
    return null;
  }
  if (town.district && (typeof town.district.id === 'number')) {
    return town.district.id;
  }
  if (typeof town.districtId === 'number') {
    return town.districtId;
  }
  return null;
}

function getDistrictColor(id: number | null, darker = false, lighten = 1) {
  if (id === null || id === undefined) return '#9CA3AF';
  const hue = (Number(id) * 57) % 360;
  const lightness = darker ? 35 : (42 * lighten);
  return `hsl(${hue}deg 72% ${lightness}%)`;
}

const agenciesToRender = computed(() => {
  return selectedAgencyId.value === null
    ? graphData
    : graphData.filter(a => a.id == selectedAgencyId.value);
});

const districtsList = computed(() => {
  const map = new Map();
  agenciesToRender.value.forEach((agency: any) => {
    (agency.delegates || []).forEach((delegate: any) => {
      (delegate.branches || []).forEach((branch: any) => {
        (branch.towns || []).forEach((town: any) => {
          const did = getDistrictId(town);
          if (did === null) return;
          if (!map.has(did)) map.set(did, { id: did, name: (town.district && town.district.name) || `District ${did}` });
        });
      });
    });
  });
  (towns || []).forEach(town => {
    const did = getDistrictId(town);
    if (did === null) return;
    if (!map.has(did)) map.set(did, { id: did, name: (town.district && town.district.name) || `District ${did}` });
  });
  return Array.from(map.values()).sort((a, b) => a.id - b.id);
});

// Build organization graph data (agencies → delegates → branches → towns)
function buildOrgGraphData() {
  const nodes: any[] = [];
  const edges: any[] = [];

  agenciesToRender.value.forEach((agency: any) => {
    nodes.push({ id: nodeId('agency', agency.id), label: agency.name, group: 'agency', level: 0 });
    const isAgencyExpanded = expandedAgencies.value.has(agency.id);
    agency.delegates.forEach((delegate: any) => {
      nodes.push({ 
        id: nodeId('delegate', delegate.id), 
        label: delegate.name, 
        group: 'delegate',
        level: 1,
        hidden: !isAgencyExpanded
      });
      edges.push({ 
        from: nodeId('agency', agency.id), 
        to: nodeId('delegate', delegate.id),
        hidden: !isAgencyExpanded
      });
      const isDelegateExpanded = expandedDelegates.value.has(delegate.id);
      delegate.branches.forEach((branch: any) => {
        nodes.push({ 
          id: nodeId('branch', branch.id), 
          label: branch.name, 
          group: 'branch',
          branchData: branch,
          level: 2,
          hidden: !isAgencyExpanded || !isDelegateExpanded
        });
        edges.push({ 
          from: nodeId('delegate', delegate.id), 
          to: nodeId('branch', branch.id),
          hidden: !isAgencyExpanded || !isDelegateExpanded
        });
        
        // Add linked towns to the organization chart
        (branch.towns || []).forEach((town: any) => {
          const did = getDistrictId(town);
          nodes.push({ 
            id: nodeId('town', town.id), 
            label: town.name, 
            group: 'linkedTown', 
            title: `${town.name} (Linked to ${branch.name})`,
            color: { 
              background: getDistrictColor(did),
              border: getDistrictColor(did, true),
              highlight: { 
                background: getDistrictColor(did, false, 1.3), 
                border: getDistrictColor(did, true) 
              }
            },
            font: { color: '#fff', size: 11 },
            townData: town,
            level: 3,
            hidden: !isAgencyExpanded || !isDelegateExpanded || !expandedBranches.value.has(branch.id)
          });
          edges.push({ 
            from: nodeId('branch', branch.id), 
            to: nodeId('town', town.id),
            hidden: !isAgencyExpanded || !isDelegateExpanded || !expandedBranches.value.has(branch.id)
          });
        });
      });
    });
  });

  return { nodes, edges };
}

// Build towns graph data (districts → towns) - exclude already linked towns
function buildTownsGraphData() {
  const nodes: any[] = [];
  const edges: any[] = [];
  const edgeKeys = new Set();

  // Collect IDs of towns that are already linked to branches
  const linkedTownIds = new Set();
  agenciesToRender.value.forEach((agency: any) => {
    (agency.delegates || []).forEach((delegate: any) => {
      (delegate.branches || []).forEach((branch: any) => {
        (branch.towns || []).forEach((town: any) => {
          linkedTownIds.add(town.id);
        });
      });
    });
  });

  // Collect all unlinked towns
  const allTowns = new Map();
  (towns || []).forEach((town: any) => {
    if (!linkedTownIds.has(town.id) && !allTowns.has(town.id)) {
      allTowns.set(town.id, town);
    }
  });

  // Group towns by district
  const districtMap: Map<number, any> = new Map();
  Array.from(allTowns.values()).forEach((town: any) => {
    const did = getDistrictId(town);
    if (did !== null) {
      if (!districtMap.has(did)) {
        districtMap.set(did, {
          id: did,
          name: (town.district && town.district.name) || `District ${did}`,
          towns: []
        });
      }
      districtMap.get(did).towns.push(town);
    }
  });

  // Create district nodes and town nodes
  Array.from(districtMap.values())
    .sort((a, b) => a.id - b.id)
    .forEach(district => {
      nodes.push({ 
        id: nodeId('district', district.id), 
        label: district.name, 
        group: 'district', 
        color: { 
          background: getDistrictColor(district.id),
          border: getDistrictColor(district.id, true),
          highlight: { 
            background: getDistrictColor(district.id, false, 1.2), 
            border: getDistrictColor(district.id, true) 
          }
        }, 
        font: { color: '#fff', size: 14 },
        fixed: { x: false, y: true },
        districtData: district
      });

      district.towns
        .sort((a: any, b: any) => (a.name || '').localeCompare(b.name || ''))
        .forEach((town: any) => {
          const isDistrictExpanded = expandedDistricts.value.has(district.id);
          nodes.push({ 
            id: nodeId('town', town.id), 
            label: town.name, 
            group: 'town', 
            districtId: district.id, 
            title: `${town.name} — ${district.name}`, 
            color: { 
              background: getDistrictColor(district.id),
              border: getDistrictColor(district.id, true),
              highlight: { 
                background: getDistrictColor(district.id, false, 1.3), 
                border: getDistrictColor(district.id, true) 
              }
            },
            font: { color: '#fff', size: 12 },
            townData: town,
            hidden: !isDistrictExpanded
          });
          
          const edgeKey = `${nodeId('district', district.id)}->${nodeId('town', town.id)}`;
          if (!edgeKeys.has(edgeKey)) {
            edges.push({ 
              from: nodeId('district', district.id), 
              to: nodeId('town', town.id),
              hidden: !isDistrictExpanded
            });
            edgeKeys.add(edgeKey);
          }
        });
    });

  return { nodes, edges };
}

async function renderOrgNetwork() {
  const { Network, DataSet } = await import('vis-network/standalone');
  const container = document.getElementById('org-network');
  if (!container) return;

  const graphData = buildOrgGraphData();
  const nodesDataSet = new DataSet(graphData.nodes);
  const edgesDataSet = new DataSet(graphData.edges);
  
  const data = {
    nodes: nodesDataSet,
    edges: edgesDataSet
  };

  const options = {
    groups: {
      agency: { 
        shape: 'box', 
        color: { 
          background: '#1e40af', 
          border: '#1e3a8a',
          highlight: { background: '#3b82f6', border: '#2563eb' }
        }, 
        font: { color: '#fff', size: 16 } 
      },
      delegate: { 
        shape: 'box', 
        color: { 
          background: '#4b5563', 
          border: '#374151',
          highlight: { background: '#6b7280', border: '#4b5563' }
        }, 
        font: { color: '#fff', size: 14 } 
      },
      branch: { 
        shape: 'ellipse', 
        color: { 
          background: '#0891b2', 
          border: '#0e7490',
          highlight: { background: '#06b6d4', border: '#0891b2' }
        }, 
        font: { color: '#fff', size: 13 }, 
        size: 30 
      },
      linkedTown: { 
        shape: 'box', 
        font: { color: '#fff', size: 11, bold: true }, 
        borderWidth: 2,
        shadow: { enabled: true, color: 'rgba(0,0,0,0.1)', size: 3 },
        margin: 6
      },
    },
    layout: {
      hierarchical: {
        direction: 'UD',
        sortMethod: 'directed',
        nodeSpacing: 180,
        levelSeparation: 120,
        shakeTowards: 'leaves'
      }
    },
    physics: { enabled: false },
    interaction: { 
      hover: true, 
      multiselect: false, 
      tooltipDelay: 100, 
      hideEdgesOnDrag: false, 
      hideEdgesOnZoom: false,
      dragNodes: false,
      dragView: true,
      zoomView: true
    },
    edges: { 
      smooth: { enabled: true, type: 'continuous', roundness: 0.5 }, 
      color: { color: '#9ca3af', highlight: '#6b7280' },
      width: 2
    },
  };

  if (orgNetworkRef.value) {
    try { orgNetworkRef.value.destroy(); } catch { /* ignore */ }
    container.innerHTML = '';
  }

  orgNetworkRef.value = new Network(container, data, options);

  // Click handler: toggle agency expand/collapse or select branch
  orgNetworkRef.value.on('click', params => {
    if (params.nodes && params.nodes.length) {
      const nodeId = params.nodes[0];
      
      // Toggle agency expand/collapse
      if (nodeId.startsWith('agency-')) {
        const agencyId = parseInt(nodeId.split('-')[1]);
        const allNodes = nodesDataSet.get();
        const allEdges = edgesDataSet.get();
        
        // Find delegates under this agency
        const delegatesToToggle = allNodes.filter(n => n.group === 'delegate' && 
          allEdges.some(e => e.from === nodeId && e.to === n.id));
        
        if (delegatesToToggle.length) {
          const shouldHide = !delegatesToToggle[0].hidden;
          
          // Update expanded state
          if (shouldHide) {
            expandedAgencies.value.delete(agencyId);
          } else {
            expandedAgencies.value.add(agencyId);
          }
          saveExpandedState('agencies', expandedAgencies.value);
          
          // Batch all updates
          const nodesToUpdate: { id: string | number; hidden: boolean }[] = [];
          const edgesToUpdate: { id: string | number; hidden: boolean }[] = [];
          
          delegatesToToggle.forEach(delegate => {
            nodesToUpdate.push({ id: delegate.id, hidden: shouldHide });
            
            // Toggle edge from agency to delegate
            const agencyEdge = allEdges.find(e => e.from === nodeId && e.to === delegate.id);
            if (agencyEdge) {
              edgesToUpdate.push({ id: agencyEdge.id, hidden: shouldHide });
            }
            
            // Toggle branches under this delegate
            const branchesToToggle = allNodes.filter(n => n.group === 'branch' && 
              allEdges.some(e => e.from === delegate.id && e.to === n.id));
            
            branchesToToggle.forEach(branch => {
              nodesToUpdate.push({ id: branch.id, hidden: shouldHide });
              const branchEdge = allEdges.find(e => e.from === delegate.id && e.to === branch.id);
              if (branchEdge) {
                edgesToUpdate.push({ id: branchEdge.id, hidden: shouldHide });
              }
              
              // Also toggle linked towns under this branch
              const linkedTowns = allNodes.filter(n => n.group === 'linkedTown' && 
                allEdges.some(e => e.from === branch.id && e.to === n.id));
              linkedTowns.forEach(town => {
                nodesToUpdate.push({ id: town.id, hidden: shouldHide });
                const townEdge = allEdges.find(e => e.from === branch.id && e.to === town.id);
                if (townEdge) {
                  edgesToUpdate.push({ id: townEdge.id, hidden: shouldHide });
                }
              });
            });
          });
          
          // Apply all updates at once
          if (nodesToUpdate.length) nodesDataSet.update(nodesToUpdate);
          if (edgesToUpdate.length) edgesDataSet.update(edgesToUpdate);
        }
      }
      
      // Toggle delegate expand/collapse
      if (nodeId.startsWith('delegate-')) {
        const delegateId = parseInt(nodeId.split('-')[1]);
        const allNodes = nodesDataSet.get();
        const allEdges = edgesDataSet.get();
        
        // Find branches under this delegate
        const branchesToToggle = allNodes.filter(n => n.group === 'branch' && 
          allEdges.some(e => e.from === nodeId && e.to === n.id));
        
        if (branchesToToggle.length) {
          const shouldHide = !branchesToToggle[0].hidden;
          
          // Update expanded state
          if (shouldHide) {
            expandedDelegates.value.delete(delegateId);
          } else {
            expandedDelegates.value.add(delegateId);
          }
          saveExpandedState('delegates', expandedDelegates.value);
          
          // Batch all updates
          const nodesToUpdate: { id: string | number; hidden: boolean }[] = [];
          const edgesToUpdate: { id: string | number; hidden: boolean }[] = [];
          
          branchesToToggle.forEach(branch => {
            nodesToUpdate.push({ id: branch.id, hidden: shouldHide });
            const branchEdge = allEdges.find(e => e.from === nodeId && e.to === branch.id);
            if (branchEdge) {
              edgesToUpdate.push({ id: branchEdge.id, hidden: shouldHide });
            }
            
            // Also toggle linked towns under this branch
            const linkedTowns = allNodes.filter(n => n.group === 'linkedTown' && 
              allEdges.some(e => e.from === branch.id && e.to === n.id));
            linkedTowns.forEach(town => {
              nodesToUpdate.push({ id: town.id, hidden: shouldHide });
              const townEdge = allEdges.find(e => e.from === branch.id && e.to === town.id);
              if (townEdge) {
                edgesToUpdate.push({ id: townEdge.id, hidden: shouldHide });
              }
            });
          });
          
          // Apply all updates at once
          if (nodesToUpdate.length) nodesDataSet.update(nodesToUpdate);
          if (edgesToUpdate.length) edgesDataSet.update(edgesToUpdate);
        }
      }
      
      // Toggle branch expand/collapse (for linked towns)
      if (nodeId.startsWith('branch-')) {
        const branchId = parseInt(nodeId.split('-')[1]);
        const allNodes = nodesDataSet.get();
        const allEdges = edgesDataSet.get();
        
        // Find linked towns under this branch
        const linkedTowns = allNodes.filter(n => n.group === 'linkedTown' && 
          allEdges.some(e => e.from === nodeId && e.to === n.id));
        
        if (linkedTowns.length) {
          const shouldHide = !linkedTowns[0].hidden;
          
          // Update expanded state
          if (shouldHide) {
            expandedBranches.value.delete(branchId);
          } else {
            expandedBranches.value.add(branchId);
          }
          saveExpandedState('branches', expandedBranches.value);
          
          // Batch all updates
          const nodesToUpdate: { id: string | number; hidden: boolean }[] = [];
          const edgesToUpdate: { id: string | number; hidden: boolean }[] = [];
          
          linkedTowns.forEach(town => {
            nodesToUpdate.push({ id: town.id, hidden: shouldHide });
            const townEdge = allEdges.find(e => e.from === nodeId && e.to === town.id);
            if (townEdge) {
              edgesToUpdate.push({ id: townEdge.id, hidden: shouldHide });
            }
          });
          
          // Apply all updates at once
          if (nodesToUpdate.length) nodesDataSet.update(nodesToUpdate);
          if (edgesToUpdate.length) edgesDataSet.update(edgesToUpdate);
        }
        
        // Also select branch for linking
        let node = nodesDataSet.get(nodeId);
        if (Array.isArray(node)) {
          node = node[0];
        }
        if (node && typeof node === 'object' && 'branchData' in node) {
          selectedBranch.value = (node as any).branchData;
        }
      }
    }
  });
}

async function renderTownsNetwork() {
  const { Network, DataSet } = await import('vis-network/standalone');
  const container = document.getElementById('towns-network');
  if (!container) return;

  const graphData = buildTownsGraphData();
  const nodesDataSet = new DataSet(graphData.nodes);
  const edgesDataSet = new DataSet(graphData.edges);
  
  const data = {
    nodes: nodesDataSet,
    edges: edgesDataSet
  };

  const options = {
    groups: {
      district: { 
        shape: 'box', 
        font: { color: '#fff', size: 14, bold: true }, 
        size: 35,
        fixed: { x: false, y: true },
        borderWidth: 2,
        shadow: { enabled: true, color: 'rgba(0,0,0,0.1)', size: 5 }
      },
      town: { 
        shape: 'box', 
        font: { color: '#fff', size: 12, bold: true }, 
        borderWidth: 2,
        shadow: { enabled: true, color: 'rgba(0,0,0,0.1)', size: 3 },
        margin: 8
      },
    },
    layout: {
      hierarchical: {
        direction: 'UD',
        sortMethod: 'directed',
        nodeSpacing: 150,
        levelSeparation: 120,
        shakeTowards: 'leaves'
      }
    },
    physics: { enabled: false },
    interaction: { 
      hover: true, 
      multiselect: false, 
      tooltipDelay: 100,
      dragNodes: false,
      dragView: true,
      zoomView: true,
      hideEdgesOnDrag: false,
      hideEdgesOnZoom: false
    },
    manipulation: {
      enabled: false
    },
    edges: { 
      smooth: { enabled: true, type: 'continuous', roundness: 0.5 }, 
      color: { color: '#9ca3af', highlight: '#6b7280' },
      width: 2
    },
  };

  if (townsNetworkRef.value) {
    try { townsNetworkRef.value.destroy(); } catch { /* ignore */ }
    container.innerHTML = '';
  }

  townsNetworkRef.value = new Network(container, data, options);

  // Click handler: toggle district expand/collapse OR link town to branch
  townsNetworkRef.value.on('click', params => {
    if (params.nodes && params.nodes.length) {
      const nodeId = params.nodes[0];
      
      // Toggle district expand/collapse
      if (nodeId.startsWith('district-')) {
        const districtId = parseInt(nodeId.split('-')[1]);
        const allNodes = nodesDataSet.get();
        const allEdges = edgesDataSet.get();
        
        // Find towns under this district
        const townsToToggle = allNodes.filter(n => n.group === 'town' && n.districtId == districtId);
        
        if (townsToToggle.length) {
          const shouldHide = !townsToToggle[0].hidden;
          
          // Update expanded state
          if (shouldHide) {
            expandedDistricts.value.delete(districtId);
          } else {
            expandedDistricts.value.add(districtId);
          }
          saveExpandedState('districts', expandedDistricts.value);
          
          // Batch all updates
          const nodesToUpdate: { id: string | number; hidden: boolean }[] = [];
          const edgesToUpdate: { id: string | number; hidden: boolean }[] = [];
          
          townsToToggle.forEach(town => {
            nodesToUpdate.push({ id: town.id, hidden: shouldHide });
            
            // Toggle edge from district to town
            const townEdge = allEdges.find(e => e.from === nodeId && e.to === town.id);
            if (townEdge) {
              edgesToUpdate.push({ id: townEdge.id, hidden: shouldHide });
            }
          });
          
          // Apply all updates at once
          if (nodesToUpdate.length) nodesDataSet.update(nodesToUpdate);
          if (edgesToUpdate.length) edgesDataSet.update(edgesToUpdate);
        }
      }
      
      // Link town to selected branch
      if (nodeId.startsWith('town-') && selectedBranch.value) {
        let node = nodesDataSet.get(nodeId);
        if (Array.isArray(node)) {
          node = node[0];
        }
        console.log('Town clicked:', { nodeId, node, selectedBranch: selectedBranch.value });
        
        // Unwrap node if it's an array
        const nodeObj = Array.isArray(node) ? node[0] : node;
        if (nodeObj && nodeObj.townData) {
          const townId = nodeObj.townData.id;
          const branchId = selectedBranch.value.id;
          
          console.log('Attempting to link:', { townId, branchId, townName: nodeObj.townData.name, branchName: selectedBranch.value.name });
          
          axios.post(`/admin/branch/${branchId}/towns`, { town_id: townId })
            .then(response => {
              console.log('Link successful:', response.data);
              // Show success message
              const branchName = selectedBranch.value ? selectedBranch.value.name : '';
              const message = `✓ Successfully linked "${nodeObj.townData.name}" to "${branchName}"`;
              alert(message);
              // Expand the branch to show the newly linked town
              expandedBranches.value.add(branchId);
              saveExpandedState('branches', expandedBranches.value);
              // Reload data using Inertia
              router.visit(window.location.href, {
                preserveScroll: true,
                preserveState: false,
                only: ['graph', 'towns']
              });
            })
            .catch(err => {
              console.error('Failed to link town to branch', err);
              const errorMsg = err.response?.data?.message || err.message || 'Failed to link town to branch';
              alert(`✗ Error: ${errorMsg}`);
            });
        } else {
          console.warn('Node or townData missing', { node });
        }
      } else if (nodeId.startsWith('town-') && !selectedBranch.value) {
        alert('⚠️ Please select a branch first from the organization chart above');
      }
    }
  });
}

async function renderNetworks() {
  await renderOrgNetwork();
  await renderTownsNetwork();
}

onMounted(async () => {
  await renderNetworks();
});

watch(selectedAgencyId, () => {
  renderNetworks();
});

// Re-render when graph data changes (after Inertia reload)
watch(() => page.props.graph, (newVal, oldVal) => {
  if (newVal && JSON.stringify(newVal) !== JSON.stringify(oldVal)) {
    renderNetworks();
  }
}, { deep: true });
</script>

<template>
  <AdminLayout>
    <div class="p-6 bg-white dark:bg-slate-900 min-h-screen">
    <h1 class="text-2xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Organization Graph</h1>

    <div class="flex gap-6">
      <!-- Sidebar -->
      <aside class="w-80 bg-white dark:bg-slate-800 rounded-lg p-4 shadow border border-gray-200 dark:border-slate-700">
        <!-- Agency Filter -->
        <div class="mb-6">
          <div class="mb-3 font-medium text-gray-900 dark:text-gray-100">Select Agency</div>
          <div class="space-y-2 overflow-auto max-h-48">
            <button
              class="w-full text-left px-3 py-2 rounded text-sm hover:bg-gray-100 dark:hover:bg-slate-700 text-gray-900 dark:text-gray-100"
              :class="{ 'bg-gray-100 dark:bg-slate-700': selectedAgencyId === null }"
              @click="selectAgency(null)">
              Show All Agencies
            </button>
            <template v-for="agency in graphData" :key="agency.id">
              <button
                class="w-full text-left px-3 py-2 rounded text-sm hover:bg-gray-100 dark:hover:bg-slate-700 text-gray-900 dark:text-gray-100"
                :class="{ 'bg-blue-100 dark:bg-blue-900/50 font-semibold': selectedAgencyId === agency.id }"
                @click="selectAgency(agency.id)">
                {{ agency.name }}
              </button>
            </template>
          </div>
        </div>

        <!-- Districts Legend -->
        <div class="mb-6">
          <div class="mb-3 font-medium text-gray-900 dark:text-gray-100">Districts</div>
          <div class="space-y-1 max-h-32 overflow-auto">
            <template v-for="d in districtsList" :key="d.id">
              <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <span :style="{ background: getDistrictColor(d.id) }" class="w-4 h-4 rounded-sm border border-gray-300 dark:border-gray-600"></span>
                <span class="truncate">{{ d.name }}</span>
              </div>
            </template>
          </div>
        </div>

        <!-- Selected Branch -->
        <div v-if="selectedBranch" class="p-3 bg-blue-50 dark:bg-blue-900/30 rounded-lg border border-blue-200 dark:border-blue-800">
          <div class="text-xs font-medium text-blue-600 dark:text-blue-400 mb-1">Selected Branch:</div>
          <div class="text-sm font-semibold text-blue-900 dark:text-blue-300">{{ selectedBranch.name }}</div>
          <div class="mt-2 text-xs text-blue-600 dark:text-blue-400">
            👉 Click a town below to link it to this branch
          </div>
          <button 
            @click="selectedBranch = null" 
            class="mt-2 text-xs text-red-600 dark:text-red-400 hover:underline">
            Clear Selection
          </button>
        </div>

        <!-- Instructions -->
        <div v-else class="p-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg border border-gray-200 dark:border-slate-600">
          <div class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">How to Link Towns:</div>
          <ol class="text-xs text-gray-600 dark:text-gray-400 space-y-1 list-decimal list-inside">
            <li>Click on a <strong>branch</strong> in the top graph</li>
            <li>Expand a <strong>district</strong> in the bottom graph</li>
            <li>Click on a <strong>town</strong> to link it</li>
          </ol>
        </div>
      </aside>

      <!-- Main Content -->
      <div class="flex-1 space-y-4">
        <!-- Organization Graph -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow border border-gray-200 dark:border-slate-700 overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900/50">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Organization Structure</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Click agencies to expand, click branches to select</p>
          </div>
          <div id="org-network" class="bg-white dark:bg-slate-900" style="height: 35vh;"></div>
        </div>

        <!-- Districts & Towns Graph -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow border border-gray-200 dark:border-slate-700 overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900/50">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Districts & Towns</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
              <span v-if="selectedBranch" class="text-blue-600 dark:text-blue-400 font-medium">Click a town to link to {{ selectedBranch.name }}</span>
              <span v-else>Select a branch above first, then click a town to link</span>
            </p>
          </div>
          <div id="towns-network" class="bg-white dark:bg-slate-900" style="height: 35vh;"></div>
        </div>
      </div>
    </div>
  </div>
  </AdminLayout>
</template>

<style scoped>
/* Graph containers inherit background from parent divs */
</style>
