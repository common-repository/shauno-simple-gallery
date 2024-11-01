<div class="ssg-gallery-container">
	<h2><?php echo $gallery->name; ?></h2>
	<?php foreach ((array)$images as $key=>$val) { ?>
		<a class="ssg-gallery-image" href="<?php echo $gallery->url.'/'.$val->filename; ?>">
			<img src="<?php echo $gallery->url.'/'.$this->thumb(array('src'=>$gallery->directory.'/'.$val->filename, 'width'=>160, 'height'=>120, 'crop'=>true)) ?>" />
		</a>
	<?php } ?>
</div> <!-- /.ssg-gallery-container -->