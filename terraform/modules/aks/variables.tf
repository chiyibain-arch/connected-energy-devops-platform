variable "environment" {
  description = "Deployment environment such as dev, stage, or prod"
  type        = string
}

variable "project_name" {
  description = "Base project name used for AKS resource naming"
  type        = string
}

variable "location" {
  description = "Azure region where the AKS cluster is deployed"
  type        = string
}

variable "resource_group_name" {
  description = "Resource group containing the AKS cluster"
  type        = string
}

variable "aks_subnet_id" {
  description = "ID of the existing subnet (from the network module) used by the AKS node pool"
  type        = string
}

variable "log_analytics_workspace_id" {
  description = "ID of the Log Analytics Workspace (from the monitoring module) used for Container Insights"
  type        = string
}

variable "node_count" {
  description = "Number of nodes in the default AKS node pool"
  type        = number
}

variable "vm_size" {
  description = "VM size used by the default AKS node pool"
  type        = string
}

variable "os_disk_size_gb" {
  description = "OS disk size in GB for AKS nodes"
  type        = number
  default     = 30
}

variable "kubernetes_version" {
  description = "Kubernetes version for the AKS cluster. Leave null to use the current AKS default version"
  type        = string
  default     = null
}

variable "network_plugin" {
  description = "Kubernetes network plugin used by the AKS cluster"
  type        = string
  default     = "azure"
}

variable "network_policy" {
  description = "Kubernetes network policy engine used by the AKS cluster"
  type        = string
  default     = "azure"
}

variable "service_cidr" {
  description = "CIDR used for Kubernetes service IPs. Must not overlap with the VNet address space"
  type        = string
  default     = "10.100.0.0/16"
}

variable "dns_service_ip" {
  description = "IP address assigned to the Kubernetes DNS service, must be within service_cidr"
  type        = string
  default     = "10.100.0.10"
}

variable "tags" {
  description = "Common governance and ownership tags applied to Azure resources"
  type        = map(string)
}
