export const StatusBarComponent = {

	template: `
	<div id="importer-progress">
		<div class="progress-bar">
			<span class="progress-bar-fill" :style="{ width: completed_percentage + '%' }"></span>
		</div>
	</div>
	`,
	data: function() {
		return {
		}
	},
	props: [ 'completed_percentage' ]
}
