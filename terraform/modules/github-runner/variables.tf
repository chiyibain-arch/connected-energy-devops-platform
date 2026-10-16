variable "project_name" {
  description = "Project name used for Azure resource naming"
  type        = string
}

variable "environment" {
  description = "Deployment environment: dev, stage, or prod"
  type        = string

  validation {
    condition = contains(
      ["dev", "stage", "prod"],
      var.environment
    )

    error_message = "Environment must be dev, stage, or prod."
  }
}

variable "location" {
  description = "Azure region where runner resources are deployed"
  type        = string
}

variable "resource_group_name" {
  description = "Existing Azure Resource Group name"
  type        = string
}

variable "subnet_id" {
  description = "Azure subnet ID for GitHub Actions runner"
  type        = string
}

variable "vm_size" {
  description = "Azure VM size for GitHub Actions runner"
  type        = string
  default     = "Standard_D2as_v7"
}

variable "admin_username" {
  description = "Administrator username for Linux runner VM"
  type        = string
  default     = "azureadmin"
}

variable "ssh_public_key" {
  description = "SSH public key used for secure VM authentication"
  type        = string
  sensitive   = true
}

variable "tags" {
  description = "Azure governance, ownership, and cost tags"
  type        = map(string)

  default = {}
}
