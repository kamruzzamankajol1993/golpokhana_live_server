<div class="modal fade progga-modal" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Food Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editCategoryForm" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <input type="hidden" id="edit_category_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="progga-form-group">
                                <label class="progga-form-label">Category Name <span class="progga-required">*</span></label>
                                <input type="text" name="name" id="edit_name" class="progga-form-control" required>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="progga-form-group">
                                <label class="progga-form-label">Parent Category</label>
                                <select name="parent_category_id" id="edit_parent_id" class="progga-select progga-choices">
                                    <option value="">None (Make it a Main Category)</option>
                                    @foreach($parentCategories as $parent)
                                        <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-12" style="display: none;">
                            <div class="progga-form-group">
                                <label class="progga-form-label">Slug</label>
                                <input type="text" name="slug" id="edit_slug" class="progga-form-control" readonly>
                                <div class="progga-form-hint">Slug is auto-generated and handled by the system.</div>
                            </div>
                        </div>
<div class="col-12">
    <div class="progga-form-group">
        <label class="progga-form-label">Sort Order</label>
        <input type="number" name="sort_order" id="edit_sort_order" class="progga-form-control" min="0">
    </div>
</div>
                        <div class="col-12">
                            <div class="progga-form-group">
                                <label class="progga-form-label">Category Image (100x100)</label>
                                <div class="progga-upload-row">
                                    <label class="progga-upload-thumb" for="editCatImage" style="width:100px; height:100px;">
                                        <img id="editCatPreview" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;border-radius:8px;">
                                        <span class="progga-upload-thumb-placeholder" id="editCatPlaceholder">
                                            <i class="bi bi-image" style="font-size:22px;color:var(--progga-text-muted);"></i>
                                        </span>
                                    </label>
                                    <div class="progga-upload-info">
                                        <label class="progga-btn progga-btn-outline progga-btn-sm" for="editCatImage" style="cursor:pointer;">
                                            <i class="bi bi-upload"></i> Change Image
                                        </label>
                                        <input type="file" id="editCatImage" name="image" accept="image/*" style="display:none;"
                                            onchange="previewImage(this, 'editCatPreview', 'editCatPlaceholder')">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="progga-form-group">
                                <label class="progga-form-label">Status</label>
                                <label class="progga-toggle" style="margin-top:8px;">
                                    <input type="checkbox" name="is_active" id="edit_status" value="1">
                                    <span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span>
                                    <span class="progga-toggle-label">Active</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-check-lg"></i> Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>
