<div
  class="modal fade profile-crop-modal"
  id="profileImageCropperModal"
  tabindex="-1"
  aria-labelledby="profileImageCropperModalLabel"
  aria-hidden="true"
  data-profile-crop-modal
>
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header border-0 pb-0">
        <div>
          <span class="section-badge mb-3">
            <i class="bi bi-crop"></i>
            Profile Picture Cropper
          </span>
          <h2 class="h4 mb-1" id="profileImageCropperModalLabel">Crop your profile picture</h2>
          <p class="text-muted mb-0">Zoom, drag, reposition, and save a professional square avatar.</p>
        </div>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body pt-4">
        <div class="row g-4">
          <div class="col-lg-8">
            <div class="profile-crop-stage">
              <img src="" alt="Profile image cropping workspace" class="profile-crop-source" data-profile-crop-image>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="profile-crop-side-panel h-100">
              <div>
                <h3 class="h6 mb-2">Live Preview</h3>
                <p class="text-muted small mb-3">The saved version will be optimized to 300 x 300 pixels and shown in a circular avatar frame.</p>
              </div>

              <div class="profile-crop-preview-shell">
                <div class="profile-crop-preview" data-profile-crop-preview></div>
              </div>

              <div class="profile-crop-controls mt-4">
                <button type="button" class="btn btn-outline-primary rounded-pill" data-profile-crop-zoom-out>
                  <i class="bi bi-zoom-out me-2"></i>
                  Zoom Out
                </button>
                <button type="button" class="btn btn-outline-primary rounded-pill" data-profile-crop-zoom-in>
                  <i class="bi bi-zoom-in me-2"></i>
                  Zoom In
                </button>
              </div>

              <div class="profile-crop-note mt-4">
                <i class="bi bi-info-circle"></i>
                <span>Only the cropped square image will be uploaded and saved after you confirm.</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-outline-primary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary rounded-pill px-4" data-profile-crop-confirm>
          <span class="button-label">
            <i class="bi bi-check2-circle me-2"></i>
            Crop &amp; Save
          </span>
          <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
        </button>
      </div>
    </div>
  </div>
</div>
